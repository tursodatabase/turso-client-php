#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::prelude::*;
use ext_php_rs::{php_class, php_impl};
use std::collections::HashMap;
use std::sync::Mutex;
use std::time::Instant;

use crate::{
    utils::log_error::log_error_to_tmp,
    utils::runtime::runtime,
    CONNECTION_REGISTRY,
};

/// Persistent connection entry
struct PersistentEntry {
    conn_id: String,
    last_used: Instant,
}

// Global registry for persistent connections
// Key: pool_name_conn_id -> PersistentEntry
lazy_static::lazy_static! {
    static ref PERSISTENT_CONNECTIONS: Mutex<HashMap<String, PersistentEntry>> = Mutex::new(HashMap::new());
}

/// Connection pool manager for LibSQL.
///
/// Provides connection pooling for PHP-FPM environments where connections
/// should be reused across requests handled by the same worker process.
///
/// Example usage:
/// ```php
/// $pool = new LibSQLPool("my_app_pool", 10, 300);
/// // LibSQL instances created with persistent=true will be tracked here
/// $db = new LibSQL("file:db.db", persistent: true, pool: "my_app_pool");
/// ```
#[php_class]
pub struct LibSQLPool {
    /// Pool name/identifier
    name: String,
    /// Maximum connections allowed in the pool
    max_connections: usize,
    /// Idle timeout in seconds
    idle_timeout_secs: u64,
}

#[php_impl]
impl LibSQLPool {
    /// Creates a new connection pool manager.
    ///
    /// # Arguments
    ///
    /// * `name` - Unique name for this pool
    /// * `max_connections` - Maximum connections in the pool (default: 10)
    /// * `idle_timeout` - Seconds before idle connection is closed (default: 300)
    pub fn __construct(
        name: &str,
        max_connections: Option<i64>,
        idle_timeout: Option<i64>,
    ) -> Result<Self, PhpException> {
        let max_conn = max_connections.unwrap_or(10) as usize;
        let idle_timeout = idle_timeout.unwrap_or(300) as u64;

        Ok(Self {
            name: name.to_string(),
            max_connections: max_conn,
            idle_timeout_secs: idle_timeout,
        })
    }

    /// Gets the pool name.
    pub fn get_name(&self) -> Result<String, PhpException> {
        Ok(self.name.clone())
    }

    /// Gets the maximum number of connections allowed in the pool.
    pub fn get_max_connections(&self) -> Result<i64, PhpException> {
        Ok(self.max_connections as i64)
    }

    /// Gets the idle timeout in seconds.
    pub fn get_idle_timeout(&self) -> Result<i64, PhpException> {
        Ok(self.idle_timeout_secs as i64)
    }

    /// Returns the number of connections currently tracked by this pool.
    ///
    /// Note: This counts all connections ever registered with this pool name,
    /// including potentially expired ones.
    pub fn get_connection_count(&self) -> Result<i64, PhpException> {
        let persistent = PERSISTENT_CONNECTIONS.lock().map_err(|e| {
            let err_msg = format!("Failed to lock persistent connections: {}", e);
            log_error_to_tmp(&err_msg);
            PhpException::default(err_msg)
        })?;

        let count = persistent
            .values()
            .filter(|e| e.last_used.elapsed().as_secs() < self.idle_timeout_secs)
            .count();

        Ok(count as i64)
    }

    /// Cleans up expired idle connections from the pool.
    ///
    /// # Returns
    ///
    /// The number of connections that were cleaned up.
    pub fn cleanup(&self) -> Result<i64, PhpException> {
        let _rt = runtime()?;
        let mut persistent = PERSISTENT_CONNECTIONS.lock().map_err(|e| {
            let err_msg = format!("Failed to lock persistent connections: {}", e);
            log_error_to_tmp(&err_msg);
            PhpException::default(err_msg)
        })?;

        let mut cleaned = 0;
        let pool_prefix = format!("{}_", self.name);

        persistent.retain(|key, entry| {
            if key.starts_with(&pool_prefix) && entry.last_used.elapsed().as_secs() >= self.idle_timeout_secs {
                // Remove from main registry
                if let Ok(mut registry) = CONNECTION_REGISTRY.lock() {
                    registry.remove(&entry.conn_id);
                    cleaned += 1;
                }
                false // Remove from persistent map
            } else {
                true // Keep
            }
        });

        Ok(cleaned)
    }

    /// Registers a connection with this pool.
    ///
    /// This is called internally by LibSQL when created with persistent=true.
    ///
    /// # Arguments
    ///
    /// * `conn_id` - The connection ID to register
    ///
    /// # Returns
    ///
    /// Ok(()) on success.
    pub fn register_connection(&self, conn_id: &str) -> Result<(), PhpException> {
        let mut persistent = PERSISTENT_CONNECTIONS.lock().map_err(|e| {
            let err_msg = format!("Failed to lock persistent connections: {}", e);
            log_error_to_tmp(&err_msg);
            PhpException::default(err_msg)
        })?;

        let key = format!("{}_{}", self.name, conn_id);
        persistent.insert(
            key,
            PersistentEntry {
                conn_id: conn_id.to_string(),
                last_used: Instant::now(),
            },
        );

        Ok(())
    }

    /// Updates the last-used timestamp for a connection (heartbeat).
    ///
    /// # Arguments
    ///
    /// * `conn_id` - The connection ID to update
    pub fn heartbeat(&self, conn_id: &str) -> Result<(), PhpException> {
        let mut persistent = PERSISTENT_CONNECTIONS.lock().map_err(|e| {
            let err_msg = format!("Failed to lock persistent connections: {}", e);
            log_error_to_tmp(&err_msg);
            PhpException::default(err_msg)
        })?;

        let key = format!("{}_{}", self.name, conn_id);
        if let Some(entry) = persistent.get_mut(&key) {
            entry.last_used = Instant::now();
        }

        Ok(())
    }

    /// Closes all connections managed by this pool.
    ///
    /// # Returns
    ///
    /// Ok(()) on success.
    pub fn close_all(&self) -> Result<(), PhpException> {
        let rt = runtime()?;
        let mut persistent = PERSISTENT_CONNECTIONS.lock().map_err(|e| {
            let err_msg = format!("Failed to lock persistent connections: {}", e);
            log_error_to_tmp(&err_msg);
            PhpException::default(err_msg)
        })?;

        let pool_prefix = format!("{}_", self.name);

        for (key, entry) in persistent.iter() {
            if key.starts_with(&pool_prefix) {
                if let Ok(mut registry) = CONNECTION_REGISTRY.lock() {
                    if let Some(conn) = registry.get(&entry.conn_id) {
                        rt.block_on(async {
                            let _ = conn.reset().await;
                        });
                    }
                    registry.remove(&entry.conn_id);
                }
            }
        }

        // Remove all entries for this pool
        persistent.retain(|key, _| !key.starts_with(&pool_prefix));

        Ok(())
    }

    /// Static method to get the global list of pool names.
    ///
    /// # Returns
    ///
    /// An array of pool names (derived from connection key prefixes).
    pub fn list_pools() -> Result<Vec<String>, PhpException> {
        let persistent = PERSISTENT_CONNECTIONS.lock().map_err(|e| {
            let err_msg = format!("Failed to lock persistent connections: {}", e);
            log_error_to_tmp(&err_msg);
            PhpException::default(err_msg)
        })?;

        let mut pools: Vec<String> = persistent
            .keys()
            .filter_map(|key| {
                // Pool names are the prefix before the UUID
                let parts: Vec<&str> = key.splitn(2, '_').collect();
                if parts.len() == 2 && parts[1].len() > 10 {
                    // Looks like a pool key (prefix_uuid)
                    Some(parts[0].to_string())
                } else {
                    None
                }
            })
            .collect();

        pools.sort();
        pools.dedup();

        Ok(pools)
    }
}
