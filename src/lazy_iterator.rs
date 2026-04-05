#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::convert::IntoZval;
use ext_php_rs::{prelude::*, types::Zval};
use libsql::Rows;
use std::sync::Mutex;

use crate::{
    utils::{
        query_params::QueryParameters,
        runtime::{convert_libsql_value_to_zval, runtime},
    },
    CONNECTION_REGISTRY, LIBSQL_ASSOC, LIBSQL_NUM,
};

/// A truly lazy streaming iterator that fetches rows one at a time from the database.
/// This avoids loading the entire result set into memory.
#[php_class]
pub struct LibSQLLazyIterator {
    /// Connection ID for looking up the connection
    conn_id: String,
    /// The SQL query being executed
    sql: String,
    /// Query parameters
    parameters: QueryParameters,
    /// Fetch mode: LIBSQL_ASSOC, LIBSQL_NUM, or LIBSQL_BOTH
    mode: i32,
    /// The live database cursor (wrapped in Mutex for interior mutability)
    rows: Option<Mutex<Rows>>,
    /// The current row data (pre-computed for PHP iterator protocol)
    current_row: Mutex<Option<Zval>>,
    /// Current position counter
    counter: i64,
    /// Whether the iterator has been initialized (first row fetched)
    initialized: Mutex<bool>,
}

#[php_impl]
impl LibSQLLazyIterator {
    /// Constructor for LibSQLLazyIterator.
    ///
    /// # Arguments
    ///
    /// * `conn_id` - The connection ID string
    /// * `sql` - The SQL query string
    /// * `parameters` - Query parameters
    /// * `mode` - Fetch mode (LIBSQL_ASSOC, LIBSQL_NUM, LIBSQL_BOTH)
    ///
    /// # Returns
    ///
    /// A new instance of LibSQLLazyIterator ready for lazy streaming.
    pub fn __construct(
        conn_id: &str,
        sql: &str,
        parameters: QueryParameters,
        mode: i32,
    ) -> Result<Self, PhpException> {
        Ok(Self {
            conn_id: conn_id.to_string(),
            sql: sql.to_string(),
            parameters,
            mode,
            rows: None,
            current_row: Mutex::new(None),
            counter: 0,
            initialized: Mutex::new(false),
        })
    }

    /// Internal method to initialize the cursor if not already done.
    fn ensure_initialized(&mut self) -> Result<(), PhpException> {
        let mut initialized = self.initialized.lock().map_err(|e| {
            PhpException::default(format!("Failed to lock initialized mutex: {}", e))
        })?;

        if *initialized {
            return Ok(());
        }

        // Get the connection
        let conn = {
            let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
                PhpException::default(format!("Failed to lock connection registry: {}", e))
            })?;
            conn_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Connection not found"))?
                .clone()
        };

        let params = self.parameters.to_params();
        let sql = self.sql.clone();

        // Execute the query and get the cursor
        let rt = runtime()?;
        let rows = rt.block_on(async {
            conn.query(&sql, params)
                .await
                .map_err(|e| PhpException::from(format!("Failed to execute query: {}", e)))
        })?;

        self.rows = Some(Mutex::new(rows));
        *initialized = true;

        Ok(())
    }

    /// Internal method to fetch the next row from the cursor.
    fn fetch_next_row(&mut self) -> Result<Option<Zval>, PhpException> {
        self.ensure_initialized()?;

        let rows_guard = self.rows.as_ref().ok_or_else(|| {
            PhpException::from("Iterator cursor not initialized".to_string())
        })?;

        let mut rows = rows_guard.lock().map_err(|e| {
            PhpException::default(format!("Failed to lock rows mutex: {}", e))
        })?;

        let mode = self.mode;
        let rt = runtime()?;

        let result = rt.block_on(async {
            match rows.next().await {
                Ok(Some(row)) => {
                    let column_count = rows.column_count();
                    let mut ht = ext_php_rs::types::ZendHashTable::new();

                    if mode == LIBSQL_ASSOC {
                        for idx in 0..column_count {
                            let column_name = row.column_name(idx as i32).unwrap_or("unknown");
                            let value = row.get_value(idx).map_err(|e| {
                                PhpException::from(format!("Failed to get column value: {}", e))
                            })?;
                            let zval_value = convert_libsql_value_to_zval(value);
                            ht.insert(column_name, zval_value).map_err(|e| {
                                PhpException::from(format!("Failed to insert into array: {}", e))
                            })?;
                        }
                    } else if mode == LIBSQL_NUM {
                        for idx in 0..column_count {
                            let value = row.get_value(idx).map_err(|e| {
                                PhpException::from(format!("Failed to get column value: {}", e))
                            })?;
                            let zval_value = convert_libsql_value_to_zval(value);
                            ht.push(zval_value).map_err(|e| {
                                PhpException::from(format!("Failed to push to array: {}", e))
                            })?;
                        }
                    } else {
                        // LIBSQL_BOTH - include both string keys and numeric keys
                        for idx in 0..column_count {
                            let column_name = row.column_name(idx as i32).unwrap_or("unknown");
                            let value = row.get_value(idx).map_err(|e| {
                                PhpException::from(format!("Failed to get column value: {}", e))
                            })?;
                            let zval_value = convert_libsql_value_to_zval(value.clone());
                            ht.insert(column_name, zval_value).map_err(|e| {
                                PhpException::from(format!("Failed to insert into array: {}", e))
                            })?;
                            let zval_value_num = convert_libsql_value_to_zval(value);
                            ht.push(zval_value_num).map_err(|e| {
                                PhpException::from(format!("Failed to push to array: {}", e))
                            })?;
                        }
                    }

                    let zval = ht.into_zval(false).map_err(|e| {
                        PhpException::from(format!("Failed to convert to zval: {}", e))
                    })?;

                    Ok(Some(zval))
                }
                Ok(None) => Ok(None),
                Err(e) => Err(PhpException::from(format!("Failed to fetch next row: {}", e))),
            }
        });

        result
    }

    /// Returns the current element of the iteration.
    ///
    /// # Returns
    ///
    /// The current row as a PHP array, or null if the iterator is not valid.
    pub fn current(&self) -> Option<Zval> {
        let current = self.current_row.lock().ok()?;
        current.as_ref().map(|z| z.shallow_clone())
    }

    /// Returns the key of the current element.
    ///
    /// # Returns
    ///
    /// The current row index as an integer.
    pub fn key(&self) -> i64 {
        self.counter
    }

    /// Moves the iterator to the next element.
    ///
    /// This fetches the next row from the database cursor on demand.
    pub fn next(&mut self) {
        self.counter += 1;

        // Fetch the next row from the database
        match self.fetch_next_row() {
            Ok(row) => {
                let mut current = match self.current_row.lock() {
                    Ok(guard) => guard,
                    Err(_) => return,
                };
                *current = row;
            }
            Err(_) => {
                // On error, set current to None
                if let Ok(mut current) = self.current_row.lock() {
                    *current = None;
                }
            }
        }
    }

    /// Rewinds the iterator to the first element.
    ///
    /// Note: For database iterators, rewind resets the state so the query will be re-executed.
    pub fn rewind(&mut self) {
        self.counter = 0;

        // Reset state - re-query on first next() call
        if let Ok(mut initialized) = self.initialized.lock() {
            *initialized = false;
        }
        if let Ok(mut current) = self.current_row.lock() {
            *current = None;
        }
        self.rows = None;
    }

    /// Checks if the iterator is valid.
    ///
    /// # Returns
    ///
    /// True if there is a current row, false otherwise.
    pub fn valid(&mut self) -> bool {
        // Ensure we've fetched at least one row
        let initialized = match self.initialized.lock() {
            Ok(guard) => *guard,
            Err(_) => return false,
        };

        if !initialized {
            // Try to fetch the first row
            match self.fetch_next_row() {
                Ok(Some(row)) => {
                    if let Ok(mut current) = self.current_row.lock() {
                        *current = Some(row);
                    }
                    true
                }
                Ok(None) => false,
                Err(_) => false,
            }
        } else {
            let current = match self.current_row.lock() {
                Ok(guard) => guard,
                Err(_) => return false,
            };
            current.is_some()
        }
    }
}
