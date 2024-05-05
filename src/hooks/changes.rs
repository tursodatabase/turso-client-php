use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

/// Retrieves the number of changes made by the last executed statement for the specified connection.
///
/// # Arguments
///
/// * `conn_id` - The ID of the connection.
///
/// # Returns
///
/// Returns the number of changes made as a result of the last executed statement.
///
/// # Errors
///
/// Returns a `PhpException` if the connection is not found or an error occurs during retrieval.
pub fn get_changes(conn_id: String) -> Result<u64, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let affected_rows = runtime().block_on(async { conn.changes() });

    Ok(affected_rows)
}
