#![feature(abi_vectorcall)]
#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate lazy_static;
pub mod hooks;
pub mod providers;
pub mod result;
pub mod statement;
pub mod transaction;
pub mod utils;
extern crate ext_php_rs;
use crate::result::LibSQLResult;
use crate::statement::LibSQLStatement;
use crate::transaction::LibSQLTransaction;
use ext_php_rs::prelude::*;
use std::{collections::HashMap, sync::Mutex};
use utils::{
    config_value::ConfigValue,
    query_params::QueryParameters,
    runtime::{get_mode, parse_dsn},
};

lazy_static::lazy_static! {
    static ref CONNECTION_REGISTRY: Mutex<HashMap<String, libsql::Connection>> = Mutex::new(HashMap::new());
    static ref TRANSACTION_REGISTRY: Mutex<HashMap<String, libsql::Transaction>> = Mutex::new(HashMap::new());
    static ref STATEMENT_REGISTRY: Mutex<HashMap<String, libsql::Statement>> = Mutex::new(HashMap::new());
}

pub const LIBSQL_PHP_VERSION: &str = "1.2.1";

/// Represents the flag for opening a database in read-only mode.
pub const LIBSQL_OPEN_READONLY: i32 = 1;

/// Represents the flag for opening a database in read-write mode.
pub const LIBSQL_OPEN_READWRITE: i32 = 2;

/// Represents the flag for creating a new database if it does not exist.
pub const LIBSQL_OPEN_CREATE: i32 = 4;

pub const LIBSQL_ASSOC: i32 = 1;
pub const LIBSQL_NUM: i32 = 2;
pub const LIBSQL_BOTH: i32 = 3;
pub const LIBSQL_ALL: i32 = 4;

/// Struct representing LibSQL PHP Class.
#[php_class]
struct LibSQL {
    /// Property representing the connection mode.
    #[prop]
    mode: String,

    /// Property representing the connection ID.
    conn_id: String,

    /// Property representing the Database object.
    db: Option<libsql::Database>,
}

#[php_impl]
impl LibSQL {
    /// Represents the flag for opening a database in read-only mode.
    const OPEN_READONLY: i32 = 1;

    /// Represents the flag for opening a database in read-write mode.
    const OPEN_READWRITE: i32 = 2;

    /// Represents the flag for creating a new database if it does not exist.
    const OPEN_CREATE: i32 = 4;

    const LIBSQL_ASSOC: i32 = 1;
    const LIBSQL_NUM: i32 = 2;
    const LIBSQL_BOTH: i32 = 3;
    const LIBSQL_ALL: i32 = 4;

    /// Constructs a new `LibSQLConnection` object.
    ///
    /// # Arguments
    ///
    /// * `config` - The configuration value for the connection.
    /// * `flags` - Optional flags for the connection.
    /// * `encryption_key` - Optional encryption key for the connection.
    ///
    /// # Returns
    ///
    /// A `Result` containing the constructed `LibSQLConnection` object or a `PhpException` if an error occurs.
    pub fn __construct(
        config: ConfigValue,
        flags: Option<i32>,
        encryption_key: Option<String>,
    ) -> Result<Self, PhpException> {
        let db_flags = flags.unwrap_or(6);
        let encryption_key = encryption_key.unwrap_or_default();

        let (url, auth_token, sync_url, sync_interval, read_your_writes): (
            String,
            String,
            String,
            std::time::Duration,
            bool,
        ) = match config {
            ConfigValue::String(dsn) => {
                let dsn_parsed = match parse_dsn(&dsn) {
                    Some(dsn) => match (dsn.dbname.is_empty(), dsn.auth_token.is_empty()) {
                        (false, true) => Some((
                            dsn.dbname,
                            "".to_string(),
                            "".to_string(),
                            std::time::Duration::from_secs(5),
                            true,
                        )),
                        (false, false) => Some((
                            dsn.dbname,
                            dsn.auth_token,
                            "".to_string(),
                            std::time::Duration::from_secs(5),
                            true,
                        )),
                        (true, true) => None,
                        (true, false) => None
                    },
                    None => None,
                };

                dsn_parsed.ok_or_else(|| PhpException::default("Failed to parse DSN".into()))?
            }
            ConfigValue::Array(config) => {
                let url = config
                    .get("url")
                    .and_then(|v| v.to_string())
                    .unwrap_or_default();
                let auth_token = config
                    .get("authToken")
                    .and_then(|v| v.to_string())
                    .unwrap_or_default();
                let sync_url = config
                    .get("syncUrl")
                    .and_then(|v| v.to_string())
                    .unwrap_or_default();
                let sync_interval = config
                    .get("syncInterval")
                    .and_then(|s| s.to_long())
                    .map(std::time::Duration::from_secs)
                    .unwrap_or_else(|| std::time::Duration::from_secs(5));
                let read_your_writes = config
                    .get("read_your_writes")
                    .and_then(|v| v.to_bool())
                    .unwrap_or(true);

                (url, auth_token, sync_url, sync_interval, read_your_writes)
            }
        };

        if url.is_empty() {
            return Err(PhpException::default("URL is not defined!".into()));
        }

        let mode = get_mode(
            Some(url.clone()),
            Some(auth_token.clone()),
            Some(sync_url.clone()),
        );

        let (conn, db) = match mode.as_str() {
            "local" => {
                let conn = providers::local::create_local_connection(
                    url,
                    Some(db_flags),
                    Some(encryption_key),
                );
                (conn, None)
            }
            "remote" => {
                let conn = providers::remote::create_remote_connection(url, auth_token);
                (conn, None)
            }
            "remote_replica" => {
                
                let cleared_url = if url.starts_with("file:") {
                    url.strip_prefix("file:").unwrap().to_string()
                } else {
                    url.clone()
                };

                let (db, conn) = providers::remote_replica::create_remote_replica_connection(
                    cleared_url.clone(),
                    auth_token.clone(),
                    sync_url.clone(),
                    sync_interval.clone(),
                    read_your_writes.clone(),
                    Some(encryption_key),
                );
                (conn, Some(db))
            }
            _ => return Err(PhpException::default("Mode is not available!".into())),
        };

        let conn_id = uuid::Uuid::new_v4().to_string();
        CONNECTION_REGISTRY
            .lock()
            .unwrap()
            .insert(conn_id.clone(), conn);

        Ok(Self { mode, conn_id, db })
    }

    /// Retrieves the version of the LibSQL library.
    ///
    /// # Returns
    ///
    /// Returns a string representing the version of the LibSQL library.
    pub fn version() -> String {
        hooks::version::get_version()
    }

    /// Retrieves the number of changes made by the last executed statement.
    ///
    /// # Returns
    ///
    /// Returns the number of changes made as a result of the last executed statement.
    pub fn changes(&self) -> Result<u64, PhpException> {
        hooks::changes::get_changes(self.conn_id.to_string())
    }

    /// Checks if autocommit mode is enabled for the connection.
    ///
    /// # Returns
    ///
    /// Returns `true` if autocommit mode is enabled, otherwise `false`.
    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        hooks::is_autocommit::get_is_autocommit(self.conn_id.to_string())
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
    pub fn execute(
        &self,
        stmt: &str,
        parameters: Option<QueryParameters>,
    ) -> Result<u64, PhpException> {
        hooks::use_exec::exec(self.conn_id.to_string(), stmt, parameters)
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
        hooks::use_exec_batch::exec_batch(self.conn_id.to_string(), stmt)
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
        parameters: Option<QueryParameters>,
    ) -> Result<LibSQLResult, PhpException> {
        LibSQLResult::__construct(self.conn_id.to_string(), stmt, parameters)
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
        hooks::close::disconnect(self.conn_id.to_string())
    }

    /// Synchronizes the database for remote replica connections.
    ///
    /// This function attempts to synchronize the database if the connection mode is
    /// set to `remote_replica`. It uses asynchronous execution to perform the sync operation
    /// and returns an appropriate result based on the success or failure of the sync process.
    ///
    /// # Returns
    ///
    /// A `Result` containing:
    /// - `()`: An empty tuple on successful synchronization.
    /// - `PhpException`: An exception in case of failure.
    ///
    /// # Errors
    ///
    /// This function returns a `PhpException` in the following cases:
    /// - If the mode is not `remote_replica`.
    /// - If the database connection is not available for synchronization.
    /// - If the synchronization operation fails.
    ///
    /// # Panics
    ///
    /// This function will not panic.
    pub fn sync(&self) -> Result<(), PhpException> {
        if self.mode == "remote_replica" {
            match &self.db {
                Some(db) => utils::runtime::runtime().block_on(async {
                    db.sync()
                        .await
                        .map_err(|e| PhpException::default(format!("Sync failed: {}", e)))?;
                    Ok(())
                }),
                None => Err(PhpException::default(
                    "Database connection is not available for sync".to_string(),
                )),
            }
        } else {
            Err(PhpException::default(format!(
                "{} mode does not support sync",
                self.mode
            )))
        }
    }
}

// The function to display extension info in phpinfo().
pub extern "C" fn libsql_php_extension_info(_module: *mut ext_php_rs::zend::ModuleEntry) {
    unsafe {
        // Start the PHP info table.
        ext_php_rs::ffi::php_info_print_table_start();
        // Add rows to the PHP info table.
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Support\0".as_ptr() as *const i8,
            "Enabled\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Local Connection Support\0".as_ptr() as *const i8,
            "Enabled\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL In-Memory Connection Support\0".as_ptr() as *const i8,
            "Enabled\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Remote Connection Support\0".as_ptr() as *const i8,
            "Enabled\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Remote Replica Connection Support\0".as_ptr() as *const i8,
            "Enabled\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL PHP version\0".as_ptr() as *const i8,
            "1.2.1\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "Author\0".as_ptr() as *const i8,
            "Imam Ali Mustofa <darkterminal@duck.com>\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "GitHub\0".as_ptr() as *const i8,
            "https://github.com/darkterminal/libsql-extension\0".as_ptr() as *const i8,
        );
        // End the PHP info table.
        ext_php_rs::ffi::php_info_print_table_end();
    }
}

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module.info_function(libsql_php_extension_info)
}
