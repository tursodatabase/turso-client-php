#![feature(abi_vectorcall)]
#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
pub mod generator;
pub mod hooks;
pub mod providers;
pub mod result;
pub mod statement;
pub mod transaction;
pub mod utils;
use crate::generator::LibSQLIterator;
use crate::providers::sqld_offline_write::OfflineWriteConnection;
use crate::result::FetchResult;
use crate::result::LibSQLResult;
use crate::statement::LibSQLStatement;
use crate::transaction::LibSQLTransaction;
use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use ext_php_rs::{php_class, php_impl, php_module};
use hooks::load_extensions::ExtensionParams;
use std::{collections::HashMap, path::Path, sync::Mutex};
use utils::{
    config_value::ConfigValue,
    query_params::QueryParameters,
    runtime::{get_mode, parse_dsn},
    log_error::log_error_to_tmp
};

lazy_static::lazy_static! {
    static ref CONNECTION_REGISTRY: Mutex<HashMap<String, libsql::Connection>> = Mutex::new(HashMap::new());
    static ref OFFLINE_CONNECTION_REGISTRY: Mutex<HashMap<String, OfflineWriteConnection>> = Mutex::new(HashMap::new());
    static ref TRANSACTION_REGISTRY: Mutex<HashMap<String, libsql::Transaction>> = Mutex::new(HashMap::new());
    static ref STATEMENT_REGISTRY: Mutex<HashMap<String, libsql::Statement>> = Mutex::new(HashMap::new());
}

pub const LIBSQL_PHP_VERSION: &str = "1.6.2";

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
pub const LIBSQL_LAZY: i32 = 5;

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
    conn: Option<libsql::Connection>,
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
    const LIBSQL_LAZY: i32 = 5;

    /// Constructs a new `LibSQLConnection` object.
    ///
    /// # Arguments
    ///
    /// * `config` - The configuration value for the connection.
    /// * `sqld_offline_mode` - Optional flag to enable SQLD offline mode.
    /// * `flags` - Optional flags for the connection.
    /// * `encryption_key` - Optional encryption key for the connection.
    /// * `offline_writes` - Optional flag to enable offline writes for Turso Cloud.
    ///
    /// # Returns
    ///
    /// A `Result` containing the constructed `LibSQLConnection` object or a `PhpException` if an error occurs.
    pub fn __construct(
        config: ConfigValue,
        sqld_offline_mode: Option<bool>,
        flags: Option<i32>,
        encryption_key: Option<String>,
        offline_writes: Option<bool>,
    ) -> Result<Self, PhpException> {
        let db_flags = flags.unwrap_or(6);
        let encryption_key = encryption_key.unwrap_or_default();
        let offline_writes = offline_writes.unwrap_or(false);
        let sqld_offline_mode = sqld_offline_mode.unwrap_or(false);

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
                        (true, false) => None,
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

        let cleared_url = if url.starts_with("file:") {
            url.strip_prefix("file:").unwrap().to_string()
        } else {
            url.clone()
        };

        let mode = get_mode(
            Some(url.clone()),
            Some(auth_token.clone()),
            Some(sync_url.clone()),
        );

        let conn_id = uuid::Uuid::new_v4().to_string();

        if sqld_offline_mode && !auth_token.is_empty() && !sync_url.is_empty() {
            let offline_conn = providers::sqld_offline_write::create_sqld_offline_write_connection(
                cleared_url.clone(),
                auth_token.clone(),
                sync_url.clone(),
                Some(db_flags),
                Some(encryption_key),
            ).map_err(|e| {
                log_error_to_tmp(&format!("Offline connection creation failed: {:?}", e));
                e
            })?;

            OFFLINE_CONNECTION_REGISTRY
                .lock()
                .map_err(|e| {
                    let err_msg = format!("Mutex lock error: {}", e);
                    log_error_to_tmp(&err_msg);
                    PhpException::default(err_msg)
                })?
                .insert(conn_id.clone(), offline_conn);

            return Ok(Self {
                mode: "offline_write".to_string(),
                conn_id,
                db: None,
                conn: None,
            });
        }

        let (conn, db) = match mode.as_str() {
            "local" => {
                let conn = providers::local::create_local_connection(
                    url,
                    Some(db_flags),
                    Some(encryption_key),
                ).map_err(|e| {
                    log_error_to_tmp(&format!("Local connection failed: {:?}", e));
                    e
                })?;
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

                let (db, conn) = match offline_writes {
                    false => providers::remote_replica::create_remote_replica_connection(
                        cleared_url.clone(),
                        auth_token.clone(),
                        sync_url.clone(),
                        sync_interval.clone(),
                        read_your_writes.clone(),
                        Some(encryption_key),
                    ),
                    true => providers::offline_write::create_offline_write_connection(
                        cleared_url.clone(),
                        auth_token,
                        sync_url,
                    ),
                };
                (conn, Some(db))
            }
            _ => return Err(PhpException::default("Mode is not available!".into())),
        };

        CONNECTION_REGISTRY
            .lock()
            .map_err(|e| {
                let err_msg = format!("Mutex lock error: {}", e);
                log_error_to_tmp(&err_msg);
                PhpException::default(err_msg)
            })?
            .insert(conn_id.clone(), conn.clone());

        Ok(Self {
            mode,
            conn_id,
            db,
            conn: Some(conn),
        })
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
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            Ok(offline_conn.changes())
        } else {
            hooks::changes::get_changes(self.conn_id.to_string())
        }
    }

    /// Checks if autocommit mode is enabled for the connection.
    ///
    /// # Returns
    ///
    /// Returns `true` if autocommit mode is enabled, otherwise `false`.
    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        hooks::is_autocommit::get_is_autocommit(self.conn_id.to_string())
    }

    /// Retrieves the total number of changes made by the connection.
    ///
    /// # Returns
    ///
    /// Returns the total number of changes made by the connection.
    pub fn total_changes(&self) -> Result<u64, PhpException> {
        match &self.conn {
            Some(conn) => Ok(conn.total_changes()),
            None => Err(PhpException::from("Connection not available")),
        }
    }

    /// Retrieves the rowid of the last inserted row.
    ///
    /// # Returns
    ///
    /// Returns the rowid of the last inserted row.
    pub fn last_inserted_id(&self) -> Result<i64, PhpException> {
        match &self.conn {
            Some(conn) => Ok(conn.last_insert_rowid()),
            None => Err(PhpException::from("Connection not available")),
        }
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
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            // Remove the unused params variable and pass parameters directly
            match offline_conn.execute(stmt, parameters) {
                Ok(result) => Ok(result),
                Err(e) => Err(PhpException::from(format!("{:?}", e))),
            }
        } else {
            hooks::use_exec::exec(self.conn_id.to_string(), stmt, parameters)
        }
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
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            match offline_conn.execute_batch(stmt) {
                Ok(_) => Ok(true),
                Err(e) => Err(PhpException::from(format!("{:?}", e))),
            }
        } else {
            hooks::use_exec_batch::exec_batch(self.conn_id.to_string(), stmt)
        }
    }

    /// Executes a SQL query and returns the result.
    ///
    /// # Arguments
    ///
    /// * `stmt` - The SQL query to execute.
    /// * `parameters` - Parameters to bind to the query.
    /// * `force_remote` - Whether to force using the remote connection when online (only for sqld offline mode).
    ///
    /// # Returns
    ///
    /// Returns the result of the query execution.
    pub fn query(
        &self,
        stmt: &str,
        parameters: Option<QueryParameters>,
        force_remote: Option<bool>,
    ) -> Result<LibSQLResult, PhpException> {
        if self.mode == "offline_write" {
            // For offline write mode, we still use the LibSQLResult but we need to handle it differently
            // We'll create a special result that works with offline connections
            LibSQLResult::__construct_offline(self.conn_id.to_string(), stmt, parameters, force_remote)
        } else {
            LibSQLResult::__construct(self.conn_id.to_string(), stmt, parameters)
        }
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
        if self.mode == "offline_write" {
            let mut offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            offline_registry.remove(&self.conn_id);
            Ok(())
        } else {
            hooks::close::disconnect(self.conn_id.to_string())
        }
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
    /// Check connectivity status for offline write mode
    pub fn check_connectivity(&self) -> Result<bool, PhpException> {
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            Ok(offline_conn.check_connectivity())
        } else {
            Err(PhpException::default(
                "Connectivity check only available in offline_write mode".to_string(),
            ))
        }
    }

    
    /// Returns the number of pending operations (e.g. unsent queries)
    /// stored in the offline connection. This is only available in
    /// offline_write mode.
    ///
    /// # Returns
    ///
    /// The number of pending operations as a `usize`.
    ///
    /// # Errors
    ///
    /// A `PhpException` is returned if the mode is not `offline_write`.
    pub fn get_pending_operations_count(&self) -> Result<usize, PhpException> {
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            Ok(offline_conn.get_pending_operations_count())
        } else {
            Err(PhpException::default(
                "Pending operations only available in offline_write mode".to_string(),
            ))
        }
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
    pub fn sync(&self, log_info: Option<bool>) -> Result<(), PhpException> {
        let log_info = log_info.unwrap_or(false);

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
        } else if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            match offline_conn.manual_sync() {
                Ok(message) => {
                    if log_info {
                        println!("Sync result: {}", message);
                    }
                    Ok(())
                }
                Err(e) => Err(PhpException::default(e)),
            }
        } else {
            Err(PhpException::default(format!(
                "{} mode does not support sync",
                self.mode
            )))
        }
    }

    
    /// Checks the online status of the connection.
    ///
    /// This function returns a boolean indicating if the connection is online.
    /// Returns `true` if the connection is online, `false` otherwise.
    ///
    /// # Errors
    ///
    /// This function returns a `PhpException` in the following cases:
    /// - If the mode is not `offline_write`.
    /// - If the offline connection is not found.
    ///
    /// # Panics
    ///
    /// This function will not panic.
    pub fn is_online(&self) -> Result<bool, PhpException> {
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            Ok(offline_conn.is_online())
        } else {
            Err(PhpException::default(
                "Online status check only available in offline_write mode".to_string(),
            ))
        }
    }

    /// Enables or disables the loading of extensions for the given connection.
    ///
    /// # Arguments
    ///
    /// * `onoff` - An optional boolean parameter to enable or disable the loading of extensions.
    ///             If `None`, the current status is returned.
    ///
    /// # Returns
    ///
    /// Returns `Ok(())` if the operation is successful, otherwise returns a `PhpException`.
    ///
    /// # Errors
    ///
    /// This function returns a `PhpException` in the following cases:
    /// - If the connection is not available.
    /// - If the operation fails.
    pub fn enable_load_extension(&self, onoff: Option<bool>) -> Result<(), PhpException> {
        hooks::load_extensions::enable_load_extension(self.conn_id.to_string(), onoff)
    }

    pub fn load_extensions(
        &self,
        extension_paths: Option<ExtensionParams>,
    ) -> Result<(), PhpException> {
        let entry_point = None;

        match extension_paths {
            Some(ExtensionParams::String(extension)) => {
                hooks::load_extensions::load_extension(
                    self.conn_id.to_string(),
                    Path::new(&extension),
                    entry_point,
                )
                .unwrap();
            }
            Some(ExtensionParams::Array(extensions)) => {
                for extension in extensions {
                    hooks::load_extensions::load_extension(
                        self.conn_id.to_string(),
                        Path::new(&extension),
                        entry_point,
                    )
                    .unwrap();
                }
            }
            None => Err(PhpException::default(
                "No extension paths provided".to_string(),
            ))
            .unwrap(),
        }

        Ok(())
    }
}


/// libsql_php_extension_info is the function called by PHP when the extension is loaded.
/// This function prints the extension information to the PHP info page.
///
/// # Safety
///
/// This function is marked as `unsafe` because it calls the foreign function
/// `php_info_print_table_start` and `php_info_print_table_row`.
///
/// # Panics
///
/// This function will not panic.
pub extern "C" fn libsql_php_extension_info(_module: *mut ext_php_rs::zend::ModuleEntry) {
    unsafe {
        // Start the PHP info table.
        ext_php_rs::ffi::php_info_print_table_start();
        // Add rows to the PHP info table.
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Local Connection Support\0".as_ptr() as *const i8,
            "Support\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL In-Memory Connection Support\0".as_ptr() as *const i8,
            "Support\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Remote Connection Support\0".as_ptr() as *const i8,
            "Support\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Remote Replica Connection Support\0".as_ptr() as *const i8,
            "Support\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL Offline Writes Support\0".as_ptr() as *const i8,
            "Support\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "LibSQL PHP version\0".as_ptr() as *const i8,
            "1.6.2\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "Author\0".as_ptr() as *const i8,
            "Imam Ali Mustofa <darkterminal@duck.com>\0".as_ptr() as *const i8,
        );
        ext_php_rs::ffi::php_info_print_table_row(
            2,
            "GitHub\0".as_ptr() as *const i8,
            "https://github.com/tursodatabase/turso-client-php\0".as_ptr() as *const i8,
        );
        // End the PHP info table.
        ext_php_rs::ffi::php_info_print_table_end();
    }
}

/// This function is called when the PHP module is shutdown. It is responsible for releasing
/// any resources allocated by the module. In this case, it clears the connection, offline
/// connection, transaction, and statement registries.
extern "C" fn libsql_php_shutdown(_type: i32, _module_number: i32) -> i32 {
    if let Ok(mut registry) = CONNECTION_REGISTRY.lock() {
        registry.clear();
    } else {
        log_error_to_tmp("Failed to lock CONNECTION_REGISTRY during shutdown");
    }
    
    if let Ok(mut registry) = OFFLINE_CONNECTION_REGISTRY.lock() {
        registry.clear();
    } else {
        log_error_to_tmp("Failed to lock OFFLINE_CONNECTION_REGISTRY during shutdown");
    }
    
    if let Ok(mut registry) = TRANSACTION_REGISTRY.lock() {
        registry.clear();
    } else {
        log_error_to_tmp("Failed to lock TRANSACTION_REGISTRY during shutdown");
    }
    
    if let Ok(mut registry) = STATEMENT_REGISTRY.lock() {
        registry.clear();
    } else {
        log_error_to_tmp("Failed to lock STATEMENT_REGISTRY during shutdown");
    }
    
    0
}

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
        .info_function(libsql_php_extension_info)
        .shutdown_function(libsql_php_shutdown)
}
