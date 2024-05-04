#[allow(non_snake_case, deprecated)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate lazy_static;
pub mod providers;
pub mod statements;
pub mod transaction;
pub mod utils;
extern crate ext_php_rs;
use crate::transaction::LibSQLTransaction;
use ext_php_rs::prelude::*;
use std::{collections::HashMap, sync::Mutex};
use utils::{
    query_params::QueryParameters,
    runtime::{get_mode, runtime},
};

lazy_static::lazy_static! {
    static ref CONNECTION_REGISTRY: Mutex<HashMap<String, libsql::Connection>> = Mutex::new(HashMap::new());
    static ref TRANSACTION_REGISTRY: Mutex<HashMap<String, libsql::Transaction>> = Mutex::new(HashMap::new());
}

const LIBSQL_OPEN_READONLY: i32 = 1;
const LIBSQL_OPEN_READWRITE: i32 = 2;
const LIBSQL_OPEN_CREATE: i32 = 4;

#[php_class]
struct LibSQL {
    #[prop]
    mode: String,
    #[prop]
    conn_id: String,
}

#[php_impl]
impl LibSQL {
    const OPEN_READONLY: i32 = 1;
    const OPEN_READWRITE: i32 = 2;
    const OPEN_CREATE: i32 = 4;

    pub fn __construct(config: HashMap<String, String>) -> Result<Self, PhpException> {
        let url = config.get("url").cloned().unwrap_or("".to_string());

        let db_flags = config
            .get("flags")
            .and_then(|s| s.parse::<i32>().ok())
            .unwrap_or(6);

        let auth_token = config.get("authToken").cloned().unwrap_or("".to_string());

        let sync_url = config.get("syncUrl").cloned().unwrap_or("".to_string());

        let sync_interval = config
            .get("syncInterval")
            .and_then(|s| s.parse::<u64>().ok())
            .map(std::time::Duration::from_secs)
            .unwrap_or_else(|| std::time::Duration::from_secs(5));

        let encryption_key = config
            .get("encryptionKey")
            .cloned()
            .unwrap_or("".to_string());

        let read_your_writes = match config.get("read_your_writes") {
            Some(value) if !value.is_empty() => value.parse::<bool>().unwrap_or(true),
            _ => true,
        };

        if url.is_empty() {
            return Err(PhpException::default("URL is not defined!".into()));
        }

        let mode = get_mode(
            Some(url.clone()),
            Some(auth_token.clone()),
            Some(sync_url.clone()),
        );

        let conn = match mode.as_str() {
            "local" => {
                providers::local::create_local_connection(url, Some(db_flags), Some(encryption_key))
            }
            "remote" => providers::remote::create_remote_connection(url, auth_token),
            "remote_replica" => providers::remote_replica::create_remote_replica_connection(
                url,
                auth_token,
                sync_url,
                sync_interval,
                read_your_writes,
                Some(encryption_key),
            ),
            _ => return Err(PhpException::default("Mode is not available!".into())),
        };

        let conn_id = uuid::Uuid::new_v4().to_string();
        CONNECTION_REGISTRY
            .lock()
            .unwrap()
            .insert(conn_id.clone(), conn);

        Ok(Self { mode, conn_id })
    }

    pub fn version() -> String {
        statements::version::get_version()
    }

    pub fn changes(&self) -> Result<u64, PhpException> {
        statements::changes::get_changes(self.conn_id.to_string())
    }

    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        statements::is_autocommit::get_is_autocommit(self.conn_id.to_string())
    }

    pub fn exec(&self, stmt: &str) -> Result<bool, PhpException> {
        statements::use_exec::exec(self.conn_id.to_string(), stmt)
    }

    pub fn query(
        &self,
        stmt: &str,
        parameters: QueryParameters,
    ) -> Result<ext_php_rs::types::Zval, PhpException> {
        statements::use_query::query(self.conn_id.to_string(), stmt, parameters)
    }

    pub fn transaction(
        &self,
        behavior: Option<String>,
    ) -> Result<LibSQLTransaction, PhpException> {
        let tx_behavior = behavior
            .as_deref()
            .map(|s| s.to_uppercase())
            .unwrap_or_else(|| "DEFERRED".to_string());

        LibSQLTransaction::__construct(self.conn_id.clone(), tx_behavior)
    }

    pub fn close(&self) -> Result<(), PhpException> {
        let mut registry = CONNECTION_REGISTRY.lock().unwrap();
        if let Some(conn) = registry.remove(&self.conn_id) {
            runtime().block_on(async { conn.reset().await });
            Ok(())
        } else {
            Err(PhpException::default("Connection ID not found.".into()))
        }
    }
}

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}
