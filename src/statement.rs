#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
extern crate ext_php_rs;
use crate::{ext_php_rs::convert::IntoZval, result::LibSQLResult};
use ext_php_rs::prelude::*;

use crate::{
    utils::{query_params::QueryParameters, runtime::runtime},
    CONNECTION_REGISTRY, STATEMENT_REGISTRY,
};

/// Represents a LibSQLStatement object for executing SQL queries and managing statements.
#[php_class]
pub struct LibSQLStatement {
    /// The ID of the database connection associated with the statement.
    pub conn_id: String,
    /// The ID of the statement.
    pub stmt_id: String,
    pub stmt: String
}

#[php_impl]
impl LibSQLStatement {

    /// Constructs a new `LibSQLStatement` object.
    ///
    /// # Arguments
    ///
    /// * `conn_id` - The ID of the database connection.
    /// * `sql` - The SQL query string.
    ///
    /// # Returns
    ///
    /// A `Result` containing the constructed `LibSQLStatement` object or a `PhpException` if an error occurs.
    pub fn __construct(conn_id: String, sql: &str) -> Result<Self, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

        let conn = conn_registry
            .get(&conn_id)
            .ok_or_else(|| PhpException::from("Connection not found"))?;

        let stmt = runtime().block_on(async { conn.prepare(sql).await.unwrap() });

        let stmt_id = uuid::Uuid::new_v4().to_string();
        STATEMENT_REGISTRY
            .lock()
            .unwrap()
            .insert(stmt_id.clone(), stmt);

        Ok(Self { conn_id, stmt_id, stmt: sql.to_string() })
    }

    /// Finalizes the statement.
    ///
    /// # Returns
    ///
    /// A `Result` indicating success or a `PhpException` if an error occurs.
    pub fn finalize(&self) -> Result<(), PhpException> {
        let mut stmt_registry = STATEMENT_REGISTRY.lock().unwrap();

        let mut stmt = stmt_registry
            .remove(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;

        stmt.finalize();
        Ok(())
    }

    /// Executes the statement with the given parameters.
    ///
    /// # Arguments
    ///
    /// * `parameters` - Query parameters for the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing the number of affected rows or a `PhpException` if an error occurs.
    pub fn execute(&self, parameters: QueryParameters) -> Result<usize, PhpException> {
        let mut stmt_registry = STATEMENT_REGISTRY.lock().unwrap();

        let mut stmt = stmt_registry
            .remove(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;

        let result = runtime().block_on(async { stmt.execute(parameters.to_params()).await });
        match result {
            Ok(u_result) => Ok(u_result),
            Err(e) => Err(PhpException::from(e.to_string())),
        }
    }

    /// Executes a query with the given parameters and returns the result as a PHP value.
    ///
    /// # Arguments
    ///
    /// * `parameters` - Query parameters for the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing the query result as a PHP value or a `PhpException` if an error occurs.
    pub fn query(
        &self,
        parameters: Option<QueryParameters>,
    ) -> Result<LibSQLResult, PhpException> {
        LibSQLResult::__construct(self.conn_id.clone(), self.stmt.as_str(), parameters)
    }

    /// Resets the statement.
    ///
    /// # Returns
    ///
    /// A `Result` indicating success or a `PhpException` if an error occurs.
    pub fn reset(&self) -> Result<(), PhpException> {
        let mut stmt_registry = STATEMENT_REGISTRY.lock().unwrap();

        let mut stmt = stmt_registry
            .remove(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;

        stmt.reset();
        Ok(())
    }

    /// Gets the number of parameters in the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing the number of parameters or a `PhpException` if an error occurs.
    pub fn parameter_count(&self) -> Result<usize, PhpException> {
        let mut stmt_registry = STATEMENT_REGISTRY.lock().unwrap();

        let stmt = stmt_registry
            .remove(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;

        let result = stmt.parameter_count();
        Ok(result)
    }

    /// Gets the name of the parameter at the specified index.
    ///
    /// # Arguments
    ///
    /// * `idx` - The index of the parameter.
    ///
    /// # Returns
    ///
    /// A `Result` containing the parameter name or `None` if the parameter is not found,
    /// or a `PhpException` if an error occurs.
    pub fn parameter_name(&self, idx: i32) -> Result<Option<String>, PhpException> {
        let stmt_registry = STATEMENT_REGISTRY.lock().unwrap();

        let stmt = stmt_registry
            .get(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;

        let result = stmt.parameter_name(idx).map(|s| s.to_owned());
        Ok(result)
    }

    /// Retrieves information about the columns returned by the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing an array of column information or a `PhpException` if an error occurs.
    pub fn columns(&self) -> Result<Vec<ext_php_rs::types::Zval>, PhpException> {
        let stmt_registry = STATEMENT_REGISTRY.lock().unwrap();

        let stmt = stmt_registry
            .get(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;

        let columns = stmt.columns();
        let mut zval_columns = Vec::new();

        for column in columns {
            let mut zval_column = ext_php_rs::types::Zval::new();
            let mut column_info = std::collections::HashMap::new();

            column_info.insert("name".to_string(), column.name().into_zval(false)?);
            if let Some(origin_name) = column.origin_name() {
                column_info.insert("origin_name".to_string(), origin_name.into_zval(false)?);
            }
            if let Some(table_name) = column.table_name() {
                column_info.insert("table_name".to_string(), table_name.into_zval(false)?);
            }
            if let Some(database_name) = column.database_name() {
                column_info.insert("database_name".to_string(), database_name.into_zval(false)?);
            }
            if let Some(decl_type) = column.decl_type() {
                column_info.insert("decl_type".to_string(), decl_type.into_zval(false)?);
            }

            zval_column.set_array(column_info)?;
            zval_columns.push(zval_column);
        }

        Ok(zval_columns)
    }
}
