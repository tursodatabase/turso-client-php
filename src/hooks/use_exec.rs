use ext_php_rs::exception::PhpException;

use crate::{
    utils::{log_error::log_error_to_tmp, query_params::QueryParameters, runtime::runtime},
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
pub fn exec(
    conn_id: String,
    stmt: &str,
    parameters: Option<QueryParameters>,
) -> Result<u64, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
        let err_msg = format!("Mutex lock error: {}", e);
        log_error_to_tmp(&err_msg);
        PhpException::default(err_msg)
    })?;

    let conn = conn_registry.get(&conn_id).ok_or_else(|| {
        let err_msg = "Connection not found".to_string();
        log_error_to_tmp(&err_msg);
        PhpException::from(err_msg)
    })?;

    let params = if let Some(p) = parameters {
        p.to_params()
    } else {
        libsql::params::Params::None
    };

    let result = runtime().block_on(async { conn.execute(stmt, params).await });
    match result {
        Ok(eresult) => Ok(eresult),
        Err(e) => {
            let err_msg = format!("Execution error: {}", e);
            log_error_to_tmp(&err_msg);
            Err(PhpException::from(err_msg))
        }
    }
}
