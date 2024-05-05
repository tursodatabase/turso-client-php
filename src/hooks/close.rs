use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

/// Disconnects the specified connection.
///
/// # Arguments
///
/// * `conn_id` - The ID of the connection to disconnect.
///
/// # Returns
///
/// Returns `Ok(())` if the disconnection is successful.
///
/// # Errors
///
/// Returns a `PhpException` if the connection ID is not found or an error occurs during disconnection.
pub fn disconnect(conn_id: String) -> Result<(), PhpException> {
    let mut registry = CONNECTION_REGISTRY.lock().unwrap();
    if let Some(conn) = registry.remove(&conn_id) {
        runtime().block_on(async { conn.reset().await });
        Ok(())
    } else {
        Err(PhpException::default("Connection ID not found.".into()))
    }
}
