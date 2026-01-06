use crate::utils::log_error::log_error_to_tmp;
use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};
use ext_php_rs::exception::PhpException;

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
/// Returns a `PhpException` if the connection ID is not found or
/// an error occurs during disconnection.
pub fn disconnect(conn_id: String) -> Result<(), PhpException> {
    let mut registry = CONNECTION_REGISTRY.lock().map_err(|e| {
        let err_msg = format!("Mutex lock error: {}", e);
        log_error_to_tmp(&err_msg);
        PhpException::default(err_msg)
    })?;

    if let Some(conn) = registry.remove(&conn_id) {
        runtime().block_on(async { conn.reset().await });
        Ok(())
    } else {
        let err_msg = "Connection ID not found.".to_string();
        log_error_to_tmp(&err_msg);
        Err(PhpException::default(err_msg))
    }
}
