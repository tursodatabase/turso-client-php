use std::collections::HashMap;

use ext_php_rs::{convert::IntoZval, exception::PhpException, types::Zval};

use crate::{
    utils::{query_params::QueryParameters, result_set::ResultSet, runtime::{remove_duplicates, runtime}},
    CONNECTION_REGISTRY,
};

/// Executes an SQL query with parameters on the specified connection and returns the result set.
///
/// # Arguments
///
/// * `conn_id` - The ID of the connection.
/// * `stmt` - The SQL statement to execute.
/// * `parameters` - The parameters to bind to the statement.
///
/// # Returns
///
/// Returns a `Zval` representing the result set.
///
/// # Errors
///
/// Returns a `PhpException` if the connection is not found or an error occurs during execution.
pub fn query(
    conn_id: String,
    stmt: &str,
    parameters: Option<QueryParameters>,
) -> Result<Zval, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;
    let params = if let Some(p) = parameters {
        let params = p.to_params();
        params
    } else {
        libsql::params::Params::None
    };

    let query_result = runtime().block_on(async {
        let mut rows = conn
            .query(stmt, params)
            .await
            .map_err(|e| PhpException::from(e.to_string()))?;
        let mut results: Vec<HashMap<String, libsql::Value>> = Vec::new();
        let mut columns: Vec<String> = Vec::new();

        while let Ok(Some(row)) = rows.next().await {
            let mut result = HashMap::new();

            for idx in 0..rows.column_count() {
                let column_name = row.column_name(idx as i32).unwrap();
                let value = row.get_value(idx).unwrap();

                columns.push(column_name.to_string());
                result.insert(column_name.to_string(), value);
            }
            results.push(result);
        }

        remove_duplicates(&mut columns);

        Ok(ResultSet {
            columns,
            rows: results,
            rows_affected: conn.changes(),
            last_insert_rowid: Some(conn.last_insert_rowid()),
        })
    });

    match query_result {
        Ok(result_set) => result_set
            .into_zval(false)
            .map_err(|e| PhpException::from(e.to_string())),
        Err(e) => Err(e),
    }
}
