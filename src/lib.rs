pub mod statement;
#[allow(non_snake_case, deprecated)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate lazy_static;
pub mod providers;
pub mod statements;
pub mod transaction;
pub mod utils;
extern crate ext_php_rs;
use crate::statement::LibSQLStatement;
use crate::transaction::LibSQLTransaction;
use ext_php_rs::prelude::*;
use std::{collections::HashMap, sync::Mutex};
use utils::{query_params::QueryParameters, runtime::get_mode};

lazy_static::lazy_static! {
    static ref CONNECTION_REGISTRY: Mutex<HashMap<String, libsql::Connection>> = Mutex::new(HashMap::new());
    static ref TRANSACTION_REGISTRY: Mutex<HashMap<String, libsql::Transaction>> = Mutex::new(HashMap::new());
    static ref STATEMENT_REGISTRY: Mutex<HashMap<String, libsql::Statement>> = Mutex::new(HashMap::new());
}

/// Represents the flag for opening a database in read-only mode.
pub const LIBSQL_OPEN_READONLY: i32 = 1;

/// Represents the flag for opening a database in read-write mode.
pub const LIBSQL_OPEN_READWRITE: i32 = 2;

/// Represents the flag for creating a new database if it does not exist.
pub const LIBSQL_OPEN_CREATE: i32 = 4;


/// Struct representing LibSQL PHP Class.
#[php_class]
struct LibSQL {
    
    /// Property representing the connection mode.
    #[prop]
    mode: String,

    /// Property representing the connection ID.
    #[prop]
    conn_id: String,

}

#[php_impl]
impl LibSQL {

    /// Represents the flag for opening a database in read-only mode.
    const OPEN_READONLY: i32 = 1;

    /// Represents the flag for opening a database in read-write mode.
    const OPEN_READWRITE: i32 = 2;

    /// Represents the flag for creating a new database if it does not exist.
    const OPEN_CREATE: i32 = 4;

    /// Constructs a new `LibSQL` instance.
    ///
    /// # Arguments
    ///
    /// * `config` - A hashmap containing configuration parameters for the database connection.
    ///
    /// # Returns
    ///
    /// Returns a `Result` containing the constructed `LibSQL` instance if successful, or a `PhpException` if an error occurs.
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

    /// Retrieves the version of the LibSQL library.
    ///
    /// # Returns
    ///
    /// Returns a string representing the version of the LibSQL library.
    pub fn version() -> String {
        statements::version::get_version()
    }

    /// Retrieves the number of changes made by the last executed statement.
    ///
    /// # Returns
    ///
    /// Returns the number of changes made as a result of the last executed statement.
    pub fn changes(&self) -> Result<u64, PhpException> {
        statements::changes::get_changes(self.conn_id.to_string())
    }

    /// Checks if autocommit mode is enabled for the connection.
    ///
    /// # Returns
    ///
    /// Returns `true` if autocommit mode is enabled, otherwise `false`.
    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        statements::is_autocommit::get_is_autocommit(self.conn_id.to_string())
    }

    /// Executes a SQL statement.
    ///
    /// # Arguments
    ///
    /// * `stmt` - The SQL statement to execute.
    /// * `parameters` - Parameters to bind to the statement.
    ///
    /// # Returns
    ///
    /// Returns the number of rows affected by the execution of the statement.
    pub fn execute(&self, stmt: &str, parameters: QueryParameters) -> Result<u64, PhpException> {
        statements::use_exec::exec(self.conn_id.to_string(), stmt, parameters)
    }

    /// Executes a batch of SQL statements.
    ///
    /// # Arguments
    ///
    /// * `stmt` - The batch of SQL statements to execute.
    ///
    /// # Returns
    ///
    /// Returns `true` if the execution is successful, otherwise `false`.
    pub fn execute_batch(&self, stmt: &str) -> Result<bool, PhpException> {
        statements::use_exec_batch::exec_batch(self.conn_id.to_string(), stmt)
    }

    /// Executes a SQL query and returns the result.
    ///
    /// # Arguments
    ///
    /// * `stmt` - The SQL query to execute.
    /// * `parameters` - Parameters to bind to the query.
    ///
    /// # Returns
    ///
    /// Returns the result of the query execution.
    pub fn query(
        &self,
        stmt: &str,
        parameters: QueryParameters,
    ) -> Result<ext_php_rs::types::Zval, PhpException> {
        statements::use_query::query(self.conn_id.to_string(), stmt, parameters)
    }

    /// Initiates a transaction with the specified behavior.
    ///
    /// # Arguments
    ///
    /// * `behavior` - The behavior of the transaction.
    ///
    /// # Returns
    ///
    /// Returns a `LibSQLTransaction` instance representing the transaction.
    pub fn transaction(&self, behavior: Option<String>) -> Result<LibSQLTransaction, PhpException> {
        let tx_behavior = behavior
            .as_deref()
            .map(|s| s.to_uppercase())
            .unwrap_or_else(|| "DEFERRED".to_string());

        LibSQLTransaction::__construct(self.conn_id.clone(), tx_behavior)
    }

    /// Prepares a SQL statement for execution.
    ///
    /// # Arguments
    ///
    /// * `sql` - The SQL statement to prepare.
    ///
    /// # Returns
    ///
    /// Returns a `LibSQLStatement` instance representing the prepared statement.
    pub fn prepare(&self, sql: &str) -> Result<LibSQLStatement, PhpException> {
        LibSQLStatement::__construct(self.conn_id.clone(), sql)
    }

    /// Closes the database connection.
    ///
    /// # Returns
    ///
    /// Returns `Ok(())` if the connection is closed successfully, otherwise returns a `PhpException`.
    pub fn close(&self) -> Result<(), PhpException> {
        statements::close::disconnect(self.conn_id.to_string())
    }
}

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}
