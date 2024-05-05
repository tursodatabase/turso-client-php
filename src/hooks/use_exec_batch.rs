use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

/// Executes a batch of SQL statements on the specified connection.
///
/// # Arguments
///
/// * `conn_id` - The ID of the connection.
/// * `stmt` - The SQL statements to execute.
///
/// # Returns
///
/// Returns `true` if the batch execution is successful, otherwise returns an error.
///
/// # Errors
///
/// Returns a `PhpException` if the connection is not found or an error occurs during execution.
pub fn exec_batch(conn_id: String, stmt: &str) -> Result<bool, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let result = runtime().block_on(async { conn.execute_batch(stmt).await });
    match result {
        Ok(_) => Ok(true),
        Err(e) => Err(PhpException::from(e.to_string())),
    }
}
