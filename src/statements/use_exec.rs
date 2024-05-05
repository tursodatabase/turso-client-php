use ext_php_rs::exception::PhpException;

use crate::{
    utils::{query_params::QueryParameters, runtime::runtime},
    CONNECTION_REGISTRY,
};

/// Executes an SQL statement with parameters on the specified connection.
///
/// # Arguments
///
/// * `conn_id` - The ID of the connection.
/// * `stmt` - The SQL statement to execute.
/// * `parameters` - The parameters to bind to the statement.
///
/// # Returns
///
/// Returns the number of rows affected by the statement execution.
///
/// # Errors
///
/// Returns a `PhpException` if the connection is not found or an error occurs during execution.
pub fn exec(conn_id: String, stmt: &str, parameters: QueryParameters) -> Result<u64, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let result = runtime().block_on(async { conn.execute(stmt, parameters.to_params()).await });
    match result {
        Ok(eresult) => Ok(eresult),
        Err(e) => Err(PhpException::from(e.to_string())),
    }
}
