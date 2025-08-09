use crate::{
    providers,
    utils::{query_params::QueryParameters, runtime::runtime},
};
use libsql::Value;
use serde_json::{Map, Number, Value as JsonValue};
use std::{sync::{Arc, Mutex}, time::{Duration, Instant}};

pub struct OfflineWriteConnection {
    pub local_conn: libsql::Connection,
    pub remote_conn: libsql::Connection,
    pub remote_url: String,
    pub is_online: Arc<Mutex<bool>>,
    pub pending_operations: Arc<Mutex<Vec<PendingOperation>>>,
}

#[derive(Debug, Clone)]
pub struct PendingOperation {
    pub id: Option<i64>,
    pub sql: String,
    pub params: libsql::params::Params,
    pub operation_type: OperationType,
    pub timestamp: std::time::SystemTime,
}

#[derive(Debug, Clone)]
pub enum OperationType {
    Execute,
    ExecuteBatch,
}

/// Converts a `libsql::Value` to a `serde_json::Value`. This is used to store pending operations in the SQLite database.
///
/// # Returns
///
/// A `serde_json::Value` representing the given `libsql::Value`.
fn libsql_value_to_json(value: &Value) -> JsonValue {
    match value {
        Value::Null => JsonValue::Null,
        Value::Integer(i) => JsonValue::Number(Number::from(*i)),
        Value::Real(f) => Number::from_f64(*f)
            .map(JsonValue::Number)
            .unwrap_or(JsonValue::Null),
        Value::Text(s) => JsonValue::String(s.clone()),
        Value::Blob(b) => JsonValue::Array(
            b.iter()
                .map(|v| JsonValue::Number(Number::from(*v)))
                .collect(),
        ),
    }
}

/// Converts a `serde_json::Value` to a `libsql::Value`. This is used to parse pending operations from the SQLite database.
///
/// # Returns
///
/// A `libsql::Value` representing the given `serde_json::Value`.
fn json_to_libsql_value(json: &JsonValue) -> Value {
    match json {
        JsonValue::Null => Value::Null,
        JsonValue::Number(n) if n.is_i64() => Value::Integer(n.as_i64().unwrap()),
        JsonValue::Number(n) if n.is_f64() => Value::Real(n.as_f64().unwrap()),
        JsonValue::String(s) => Value::Text(s.clone()),
        JsonValue::Array(arr) => Value::Blob(
            arr.iter()
                .filter_map(|v| v.as_u64().map(|n| n as u8))
                .collect(),
        ),
        _ => Value::Null,
    }
}

impl OfflineWriteConnection {
    
    /// Creates a new `OfflineWriteConnection` with specified parameters.
    ///
    /// This function establishes a local and remote database connection, and prepares
    /// the local database for storing pending operations. It initializes the `OfflineWriteConnection`
    /// struct with the provided database path, authentication token, synchronization URL, and optional
    /// connection flags and encryption key. It also checks the initial online status of the remote
    /// connection and loads any pending operations from the local database.
    ///
    /// # Arguments
    ///
    /// * `db_path` - The file path to the local database.
    /// * `auth_token` - The authentication token used for the remote connection.
    /// * `sync_url` - The URL used to synchronize with the remote database.
    /// * `flags` - Optional flags for opening the local database connection.
    /// * `encryption_key` - Optional encryption key for the local database.
    ///
    /// # Returns
    ///
    /// Returns an instance of `OfflineWriteConnection` with the specified configuration.
    pub fn new(
        db_path: String,
        auth_token: String,
        sync_url: String,
        flags: Option<i32>,
        encryption_key: Option<String>,
    ) -> Self {
        let local_conn = providers::local::create_local_connection(
            db_path.clone(),
            Some(flags.unwrap_or(6)),
            Some(encryption_key.unwrap_or_default()),
        );

        runtime()
            .block_on(async {
                local_conn
                    .execute(
                        "CREATE TABLE IF NOT EXISTS libsql_pending_ops (
                            id INTEGER PRIMARY KEY,
                            sql TEXT NOT NULL,
                            params_json TEXT NOT NULL,
                            operation_type TEXT NOT NULL,
                            timestamp INTEGER NOT NULL
                        )",
                        libsql::params![],
                    )
                    .await
            })
            .expect("Failed to create pending_ops table");

        runtime()
            .block_on(async {
                local_conn
                    .execute(
                        "CREATE TABLE IF NOT EXISTS libsql_metadata (
                            key TEXT PRIMARY KEY,
                            value TEXT
                        )",
                        libsql::params![],
                    )
                    .await
            })
            .expect("Failed to create metadata table");

        let remote_conn = providers::remote::create_remote_connection(sync_url.clone(), auth_token);

        let initial_online_status = crate::utils::runtime::is_reachable(&sync_url);

        let connection = Self {
            local_conn,
            remote_conn,
            remote_url: sync_url.clone(),
            is_online: Arc::new(Mutex::new(initial_online_status)),
            pending_operations: Arc::new(Mutex::new(Vec::new())),
        };

        if initial_online_status {
            let _ = connection.initial_sync_if_needed();
        }

        connection.load_pending_operations();
        connection
    }

    /// Returns whether the remote connection is currently online.
    ///
    /// # Caching
    ///
    /// The result of this function is cached for 5 seconds. If the function is called
    /// multiple times within that time frame, the cached value is returned. This is
    /// done to avoid frequent HTTP requests to check the online status of the
    /// remote database.
    ///
    /// # Note
    ///
    /// This function does not check if the remote connection is currently available
    /// or if the last synchronization attempt was successful. It only checks if the
    /// remote URL is reachable.
    pub fn is_online(&self) -> bool {
        // Cache status for 5 seconds
        static LAST_CHECK: Mutex<Option<(Instant, bool)>> = Mutex::new(None);
        
        let mut last_check = LAST_CHECK.lock().unwrap();
        if let Some((time, status)) = *last_check {
            if time.elapsed() < Duration::from_secs(5) {
                return status;
            }
        }
        
        let current_status = crate::utils::runtime::is_reachable(&self.remote_url);
        *last_check = Some((Instant::now(), current_status));
        current_status
    }

    /// Modifies a SQL statement to add "IF NOT EXISTS" to the beginning if it starts with
    /// "CREATE TABLE", "CREATE INDEX", or "CREATE VIEW" and does not already contain "IF NOT EXISTS".
    ///
    /// # Arguments
    ///
    /// * `sql` - The SQL statement to modify.
    ///
    /// # Returns
    ///
    /// The modified SQL statement.
    fn add_if_not_exists(&self, sql: &str) -> String {
        let lower_sql = sql.to_lowercase();
        if lower_sql.starts_with("create table") && !lower_sql.contains("if not exists") {
            if let Some(idx) = lower_sql.find("table") {
                let (before, after) = sql.split_at(idx + "table".len());
                format!("{} IF NOT EXISTS{}", before, after)
            } else {
                sql.to_string()
            }
        } else if lower_sql.starts_with("create index") && !lower_sql.contains("if not exists") {
            if let Some(idx) = lower_sql.find("index") {
                let (before, after) = sql.split_at(idx + "index".len());
                format!("{} IF NOT EXISTS{}", before, after)
            } else {
                sql.to_string()
            }
        } else if lower_sql.starts_with("create view") && !lower_sql.contains("if not exists") {
            if let Some(idx) = lower_sql.find("view") {
                let (before, after) = sql.split_at(idx + "view".len());
                format!("{} IF NOT EXISTS{}", before, after)
            } else {
                sql.to_string()
            }
        } else {
            sql.to_string()
        }
    }
    
    /// Performs the initial synchronization of the remote database with the local database.
    ///
    /// This function is called when the provider is first created and is used to initialize the local
    /// database with the schema and data from the remote database. It runs only once and is used to
    /// bootstrap the local database.
    ///
    /// The function first checks if the initial sync has already been done by checking the value of the
    /// 'initial_sync_done' key in the metadata table. If the value is 'true', the function returns
    /// immediately.
    ///
    /// If the initial sync has not been done, the function retrieves the schema of the remote database
    /// and applies it to the local database. It then retrieves the data from the remote database and
    /// inserts it into the local database.
    ///
    /// Finally, the function marks the initial sync as complete by setting the value of the
    /// 'initial_sync_done' key to 'true' in the metadata table.
    ///
    /// # Returns
    ///
    /// Returns a `Result` with an `Ok` value if the initial sync is successful, or an `Err` value if
    /// there is an error.
    pub fn initial_sync_if_needed(&self) -> Result<(), libsql::Error> {
        // Check if initial sync has already been done
        let sync_done: Result<bool, libsql::Error> = runtime().block_on(async {
            let mut stmt = self.local_conn
                .prepare("SELECT value FROM libsql_metadata WHERE key = 'initial_sync_done'")
                .await?;
            let mut rows = stmt.query(libsql::params::Params::None).await?;
            
            if let Some(row) = rows.next().await? {
                let value: String = row.get(0)?;
                Ok(value == "true")
            } else {
                Ok(false)
            }
        });

        if sync_done? {
            return Ok(());
        }

        let schemas: Result<Vec<String>, libsql::Error> = runtime().block_on(async {
            let mut stmt = self.remote_conn
                .prepare("SELECT sql FROM sqlite_master WHERE type IN ('table', 'index', 'view') AND name NOT LIKE 'libsql_%'")
                .await?;
            let mut rows = stmt.query(libsql::params::Params::None).await?;
            let mut schemas = Vec::new();
            
            while let Some(row) = rows.next().await? {
                let sql: String = row.get(0)?;
                let modified_sql = self.add_if_not_exists(&sql);
                schemas.push(modified_sql);
            }
            Ok(schemas)
        });
        let schemas = schemas?;

        for sql in schemas {
            runtime().block_on(async {
                self.local_conn.execute(&sql, libsql::params::Params::None).await
            })?;
        }

        let tables: Result<Vec<String>, libsql::Error> = runtime().block_on(async {
            let mut stmt = self.remote_conn
                .prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'libsql_%'")
                .await?;
            let mut rows = stmt.query(libsql::params::Params::None).await?;
            let mut tables = Vec::new();
            
            while let Some(row) = rows.next().await? {
                let name: String = row.get(0)?;
                tables.push(name);
            }
            Ok(tables)
        });
        let tables = tables?;

        for table in tables {
            let rows: Result<Vec<Vec<Value>>, libsql::Error> = runtime().block_on(async {
                let mut stmt = self.remote_conn
                    .prepare(&format!("SELECT * FROM \"{}\"", table))
                    .await?;
                let mut rows = stmt.query(libsql::params::Params::None).await?;
                let mut data = Vec::new();
                
                while let Some(row) = rows.next().await? {
                    let col_count = row.column_count() as usize;
                    let mut values = Vec::with_capacity(col_count);
                    
                    for i in 0..col_count {
                        values.push(row.get::<Value>(i as i32)?);
                    }
                    data.push(values);
                }
                Ok(data)
            });
            let rows = rows?;

            if !rows.is_empty() {
                let columns: Result<Vec<String>, libsql::Error> = runtime().block_on(async {
                    let mut stmt = self.remote_conn
                        .prepare(&format!("PRAGMA table_info(\"{}\")", table))
                        .await?;
                    let mut rows = stmt.query(libsql::params::Params::None).await?;
                    let mut cols = Vec::new();
                    
                    while let Some(row) = rows.next().await? {
                        cols.push(row.get::<String>(1)?);
                    }
                    Ok(cols)
                });
                let columns = columns?;

                let placeholders = vec!["?"; columns.len()].join(",");
                let sql = format!(
                    "INSERT OR IGNORE INTO \"{}\" ({}) VALUES ({})",
                    table,
                    columns.join(","),
                    placeholders
                );

                for row_data in rows {
                    runtime().block_on(async {
                        self.local_conn.execute(&sql, libsql::params_from_iter(row_data)).await
                    })?;
                }
            }
        }
        
        // Mark initial sync as complete
        runtime().block_on(async {
            self.local_conn.execute(
                "INSERT OR REPLACE INTO libsql_metadata (key, value) VALUES ('initial_sync_done', 'true')",
                libsql::params![],
            )
            .await
        })?;

        Ok(())
    }

    /// Loads any pending operations from the local database. This function is called
    /// when creating a new `OfflineWriteConnection` struct, and it populates the
    /// `pending_operations` field with any operations that were previously stored
    /// in the local database. The operations are loaded from the `libsql_pending_ops`
    /// table in the order they were inserted, and they are added to the `pending_operations`
    /// field in the same order.
    fn load_pending_operations(&self) {
        let mut ops = self.pending_operations.lock().unwrap();
        ops.clear();

        let query_result = runtime().block_on(async {
            self.local_conn
                .query("SELECT id, sql, params_json, operation_type, timestamp FROM libsql_pending_ops", libsql::params![])
                .await
        });

        if let Ok(mut rows) = query_result {
            while let Ok(Some(row)) = runtime().block_on(rows.next()) {
                let id: i64 = row.get(0).unwrap();
                let sql: String = row.get(1).unwrap();
                let params_json: String = row.get(2).unwrap();
                let op_type: String = row.get(3).unwrap();
                let timestamp: i64 = row.get(4).unwrap();

                let json_value: JsonValue =
                    serde_json::from_str(&params_json).unwrap_or(JsonValue::Null);
                let params = match json_value {
                    JsonValue::Array(items) => libsql::params::Params::Positional(
                        items.iter().map(json_to_libsql_value).collect(),
                    ),
                    JsonValue::Object(map) => libsql::params::Params::Named(
                        map.iter()
                            .map(|(k, v)| (k.clone(), json_to_libsql_value(v)))
                            .collect(),
                    ),
                    _ => libsql::params::Params::None,
                };

                let operation_type = match op_type.as_str() {
                    "ExecuteBatch" => OperationType::ExecuteBatch,
                    _ => OperationType::Execute,
                };

                let timestamp =
                    std::time::UNIX_EPOCH + std::time::Duration::from_secs(timestamp as u64);

                ops.push(PendingOperation {
                    id: Some(id),
                    sql,
                    params,
                    operation_type,
                    timestamp,
                });
            }
        }
    }

    /// Saves a pending operation to the local database. The operation is stored in the
    /// `libsql_pending_ops` table, and the ID of the newly inserted row is returned.
    /// If there is an error, -1 is returned.
    ///
    /// # Arguments
    ///
    /// * `op`: The pending operation to save.
    ///
    /// # Returns
    ///
    /// The ID of the newly inserted row, or -1 if there was an error.
    fn save_pending_operation(&self, op: &PendingOperation) -> i64 {
        let params_json = match &op.params {
            libsql::params::Params::None => JsonValue::Null,
            libsql::params::Params::Positional(vec) => {
                JsonValue::Array(vec.iter().map(libsql_value_to_json).collect())
            }
            libsql::params::Params::Named(map) => {
                let mut json_map = Map::new();
                for (k, v) in map {
                    json_map.insert(k.clone(), libsql_value_to_json(v));
                }
                JsonValue::Object(json_map)
            }
        };

        let params_str = serde_json::to_string(&params_json).unwrap();
        let op_type = match op.operation_type {
            OperationType::Execute => "Execute",
            OperationType::ExecuteBatch => "ExecuteBatch",
        };
        let timestamp = op
            .timestamp
            .duration_since(std::time::UNIX_EPOCH)
            .unwrap()
            .as_secs() as i64;

        let result = runtime().block_on(async {
            self.local_conn
                .execute(
                    "INSERT INTO libsql_pending_ops (sql, params_json, operation_type, timestamp) VALUES (?, ?, ?, ?)",
                    libsql::params![
                        op.sql.clone(),
                        params_str.clone(),
                        op_type,
                        timestamp
                    ],
                )
                .await
        });

        match result {
            Ok(_) => self.local_conn.last_insert_rowid(),
            Err(_) => -1,
        }
    }

    /// Removes a pending operation from the local database by id.
    ///
    /// # Arguments
    ///
    /// * `id` - The id of the pending operation to remove.
    ///
    /// # Returns
    ///
    /// This function does not return a value.
    fn remove_pending_operation(&self, id: i64) {
        let _ = runtime().block_on(async {
            self.local_conn
                .execute(
                    "DELETE FROM libsql_pending_ops WHERE id = ?",
                    libsql::params![id],
                )
                .await
        });
    }

    /// Checks if the remote connection is reachable. If the connection is reachable,
    /// this function stores the result in the `is_online` field of the `OfflineWriteConnection`
    /// struct and returns the result. If the connection is not reachable, this function
    /// returns false.
    ///
    /// # Returns
    ///
    /// This function returns true if the remote connection is reachable, and false otherwise.
    pub fn check_connectivity(&self) -> bool {
        let is_reachable = crate::utils::runtime::is_reachable(&self.remote_url);
        *self.is_online.lock().unwrap() = is_reachable;
        is_reachable
    }

    /// Executes a SQL query.
    ///
    /// If the remote connection is reachable, this function will execute the query on the remote
    /// connection and return the result. If the remote connection is not reachable, this function
    /// will execute the query on the local connection and queue the operation to be executed on
    /// the remote connection when it is reachable.
    ///
    /// # Arguments
    ///
    /// * `sql` - The SQL query to execute.
    /// * `parameters` - Optional parameters for the query.
    ///
    /// # Returns
    ///
    /// Returns the number of rows affected by the query if the query is successful, otherwise
    /// returns an error.
    pub fn execute(
        &self,
        sql: &str,
        parameters: Option<QueryParameters>,
    ) -> Result<u64, libsql::Error> {
        let params = parameters.map(|p| p.to_params());
        
        // Always write to local first
        let local_result = runtime().block_on(async {
            self.local_conn
                .execute(sql, params.clone().unwrap_or(libsql::params::Params::None))
                .await
        })?;

        // Queue for remote sync (don't block on connectivity)
        self.queue_operation(sql, params, OperationType::Execute)?;
        
        Ok(local_result)
    }

    /// Executes a SQL batch statement on the remote database if the connection is available.
    /// If the connection is not available, it executes the statement on the local database
    /// and queues the operation to be synced with the remote database when the connection
    /// becomes available.
    ///
    /// # Arguments
    ///
    /// * `sql` - The SQL batch statement to execute.
    ///
    /// # Returns
    ///
    /// Returns `true` if the statement was successfully executed on the remote database,
    /// `false` otherwise.
    pub fn execute_batch(&self, sql: &str) -> Result<bool, libsql::Error> {
        // Always execute locally first
        runtime().block_on(async { self.local_conn.execute_batch(sql).await })?;

        // Queue for remote sync
        self.queue_operation(
            sql,
            None,
            OperationType::ExecuteBatch,
        )?;
        
        Ok(true)
    }

    fn queue_operation(
        &self,
        sql: &str,
        params: Option<libsql::params::Params>,
        op_type: OperationType,
    ) -> Result<(), libsql::Error> {
        let mut pending_op = PendingOperation {
            id: None,
            sql: sql.to_string(),
            params: params.unwrap_or(libsql::params::Params::None),
            operation_type: op_type,
            timestamp: std::time::SystemTime::now(),
        };

        pending_op.id = Some(self.save_pending_operation(&pending_op));
        self.pending_operations.lock().unwrap().push(pending_op);
        
        // Attempt async sync if online
        if self.is_online() {
            let _ = self.sync_pending_operations();
        }
        
        Ok(())
    }

    /// Executes a SQL query, always using the local database by default
    /// with an option to force a remote query when online
    ///
    /// # Arguments
    ///
    /// * `sql` - The SQL query to execute
    /// * `parameters` - Optional query parameters
    /// * `force_remote` - Whether to force using the remote connection when online
    ///
    /// # Returns
    ///
    /// Query result rows
    pub fn query(
        &self,
        sql: &str,
        parameters: Option<QueryParameters>,
        force_remote: Option<bool>,
    ) -> Result<libsql::Rows, libsql::Error> {
        let params = parameters.map(|p| p.to_params()).unwrap_or(libsql::params::Params::None);
        let force_remote = force_remote.unwrap_or(false);
        
        if force_remote && self.is_online() {
            // Use remote if forced and online
            runtime().block_on(async { self.remote_conn.query(sql, params).await })
        } else {
            // Default to local database
            runtime().block_on(async { self.local_conn.query(sql, params).await })
        }
    }

    /// Syncs any pending operations (i.e. operations that were executed on the local database
    /// while the remote connection was unavailable) with the remote database.
    ///
    /// This function will return the number of operations that were successfully synced.
    /// If there is no internet connection, this function will return an error.
    ///
    /// # Returns
    ///
    /// Returns the number of operations that were successfully synced, or an error if there
    /// is no internet connection.
    pub fn sync_pending_operations(&self) -> Result<usize, String> {
        if !self.is_online() {
            return Err("Cannot sync: no internet connection".to_string());
        }

        let mut pending_ops = self.pending_operations.lock().unwrap();
        let mut synced_count = 0;
        let mut failed_ops = Vec::new();

        for op in pending_ops.drain(..) {
            let sync_result = match op.operation_type {
                OperationType::Execute => runtime().block_on(async {
                    self.remote_conn
                        .execute(&op.sql, op.params.clone())
                        .await
                        .map(|_| ())
                }),
                OperationType::ExecuteBatch => runtime().block_on(async {
                    self.remote_conn.execute_batch(&op.sql).await.map(|_| ())
                }),
            };

            match sync_result {
                Ok(_) => {
                    if let Some(id) = op.id {
                        self.remove_pending_operation(id);
                    }
                    synced_count += 1;
                }
                Err(_) => failed_ops.push(op),
            }
        }

        // Retry failed operations later
        pending_ops.extend(failed_ops);
        Ok(synced_count)
    }

    /// Manually triggers a sync of pending operations.
    ///
    /// # Returns
    ///
    /// A string indicating the number of operations synced and the number of remaining operations.
    /// If there is an error (e.g. no internet connection), an error message is returned instead.
    pub fn manual_sync(&self) -> Result<String, String> {
        let _ = self.initial_sync_if_needed();

        match self.sync_pending_operations() {
            Ok(count) => {
                let remaining = self.pending_operations.lock().unwrap().len();
                Ok(format!(
                    "Synced {} operations, {} remaining",
                    count, remaining
                ))
            }
            Err(e) => Err(e),
        }
    }

    /// Returns the number of pending operations that need to be synced with the remote database.
    ///
    /// # Returns
    ///
    /// The number of pending operations as a `usize`.
    pub fn get_pending_operations_count(&self) -> usize {
        self.pending_operations.lock().unwrap().len()
    }

    /// Retrieves the number of changes made by the last executed statement.
    ///
    /// # Returns
    ///
    /// The number of changes made by the last executed statement as a `u64`.
    pub fn changes(&self) -> u64 {
        if self.is_online() {
            self.remote_conn.changes()
        } else {
            self.local_conn.changes()
        }
    }

    /// Retrieves the total number of changes made by the connection.
    ///
    /// # Returns
    ///
    /// The total number of changes made by the connection as a `u64`.
    pub fn total_changes(&self) -> u64 {
        if self.is_online() {
            self.remote_conn.total_changes()
        } else {
            self.local_conn.total_changes()
        }
    }

    /// Retrieves the rowid of the last inserted row.
    ///
    /// # Returns
    ///
    /// The rowid of the last inserted row as a `i64`.
    pub fn last_insert_rowid(&self) -> i64 {
        if self.is_online() {
            self.remote_conn.last_insert_rowid()
        } else {
            self.local_conn.last_insert_rowid()
        }
    }

    /// Checks if autocommit mode is enabled for the connection.
    ///
    /// # Returns
    ///
    /// `true` if autocommit mode is enabled, `false` otherwise.
    pub fn is_autocommit(&self) -> bool {
        if self.is_online() {
            self.remote_conn.is_autocommit()
        } else {
            self.local_conn.is_autocommit()
        }
    }

    /// Resets the remote and local connections to their default state.
    ///
    /// # Note
    ///
    /// If the connection is not online, only the local connection is reset.
    pub async fn reset(&self) {
        if self.is_online() {
            self.remote_conn.reset().await;
        }
        self.local_conn.reset().await;
    }
}

/// Creates a new `OfflineWriteConnection` with the specified configuration.
///
/// This function initializes a new `OfflineWriteConnection` by providing the necessary
/// parameters for establishing local and remote database connections. It also prepares
/// the local database for storing pending operations to be synced with the remote database.
///
/// # Arguments
///
/// * `db_path` - The file path to the local database.
/// * `auth_token` - The authentication token used for the remote connection.
/// * `sync_url` - The URL used to synchronize with the remote database.
/// * `flags` - Optional flags for opening the local database connection.
/// * `encryption_key` - Optional encryption key for the local database.
///
/// # Returns
///
/// Returns an instance of `OfflineWriteConnection` configured with the provided parameters.
pub fn create_sqld_offline_write_connection(
    db_path: String,
    auth_token: String,
    sync_url: String,
    flags: Option<i32>,
    encryption_key: Option<String>,
) -> OfflineWriteConnection {
    OfflineWriteConnection::new(db_path, auth_token, sync_url, flags, encryption_key)
}
