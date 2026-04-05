use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

/// Retrieves the autocommit status of the specified connection.
///
/// # Arguments
///
/// * `conn_id` - The ID of the connection.
///
/// # Returns
///
/// Returns `true` if autocommit is enabled for the connection, otherwise `false`.
///
/// # Errors
///
/// Returns a `PhpException` if the connection is not found or an error occurs during retrieval.
pub fn get_is_autocommit(conn_id: String) -> Result<bool, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
        PhpException::default(format!("Mutex lock error: {}", e))
    })?;

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let rt = runtime()?;
    let is_autocommit = rt.block_on(async { conn.is_autocommit() });
    Ok(is_autocommit)
}
