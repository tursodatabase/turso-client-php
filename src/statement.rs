#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use std::{collections::HashMap, sync::{Arc, Mutex}};
use ext_php_rs::convert::IntoZval;

use crate::{result::LibSQLResult, utils::query_params::QueryValue};
use ext_php_rs::prelude::*;
use ext_php_rs::{php_class, php_impl};

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
    pub stmt: String,
    pub params: Arc<Mutex<HashMap<String, String>>>,
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

        Ok(Self { 
            conn_id,
            stmt_id,
            stmt: sql.to_string(),
            params: Arc::new(Mutex::new(HashMap::new())),
        })
    }

    /// Binds named parameters to the statement.
    ///
    /// # Arguments
    ///
    /// * `parameters` - The parameters to bind. The keys are used as the parameter names.
    ///
    /// # Returns
    ///
    /// A `Result` containing a `()` if the binding was successful or a `PhpException` if an error occurs.
    pub fn bind_named(&self, parameters: QueryParameters) -> Result<(), PhpException> {
        let mut params = self.params.lock().expect("Failed to lock params mutex");

        if let Some(named_params) = parameters.get_named() {
            for (key, value) in named_params {
                params.insert(key.to_string(), value.to_string());
            }
        }

        Ok(())
    }

    /// Binds positional parameters to the statement.
    ///
    /// The placeholder style is automatically detected and can be one of `$`, `?`, or `@`.
    /// The index of the parameter (1-based) is used to construct the placeholder.
    ///
    /// # Arguments
    ///
    /// * `parameters` - The parameters to bind to the statement.
    ///
    /// # Returns
    ///
    /// A `Result` containing `()` if the binding was successful, or a `PhpException` if an error occurs.
    fn bind_positional(&self, parameters: QueryParameters) -> Result<(), PhpException> {
        let mut params = self.params.lock().expect("Failed to lock params mutex");
    
        // Determine the placeholder style ($, ?, or @)
        let uses_dollar = self.stmt.contains("$1"); // Check for a typical `$1` pattern
        let uses_question_mark = self.stmt.contains("?"); // Check for `?` pattern
        let uses_at_symbol = self.stmt.contains("@1"); // Check for `@1` pattern
    
        if let Some(positional_params) = parameters.get_positional() {
            for (index, value) in positional_params.iter().enumerate() {
                let key = if uses_dollar {
                    // Use `$`-based placeholders (1-based index)
                    format!("${}", index + 1)
                } else if uses_question_mark {
                    // Use `?`-based placeholders (positional, no index in the placeholder itself)
                    format!("?{}", index + 1)
                } else if uses_at_symbol {
                    // Use `@`-based placeholders (1-based index)
                    format!("@{}", index + 1)
                } else {
                    // Default to `$`-based if no clear placeholder type is found
                    format!("${}", index + 1)
                };
    
                params.insert(key, value.to_string());
            }
        }
    
        Ok(())
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
    pub fn execute(&self, parameters: Option<QueryParameters>) -> Result<usize, PhpException> {
        let mut stmt_registry = STATEMENT_REGISTRY.lock().unwrap();
    
        let mut stmt = stmt_registry
            .remove(&self.stmt_id)
            .ok_or_else(|| PhpException::from("Statement not found"))?;
    
        // Default parameters from `params` if none are provided
        let params = match parameters {
            Some(p) => p,
            None => {
                // Default to the current parameters stored in the object
                let params = self.params.lock().unwrap();
                QueryParameters {
                    positional: Some(
                        params.iter().map(|(_, value)| QueryValue::Text(value.clone())).collect()
                    ),
                    named: None,
                }
            }
        };
    
        // Here we can decide which parameter type is being passed and bind them accordingly
        if let Some(params) = params.get_named() {
            let query_params = QueryParameters {
                named: Some(params.clone()),
                positional: None,
            };
            self.bind_named(query_params)?;
        }
    
        if let Some(params) = params.get_positional() {
            let query_params = QueryParameters {
                positional: Some(params.clone()),
                named: None,
            };
            self.bind_positional(query_params)?;
        }
    
        // Execute query asynchronously
        let result = runtime().block_on(async { stmt.execute(params.to_params()).await });
    
        // Clear the params after execution
        self.params.lock().unwrap().clear();
    
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
        let params = match parameters {
            Some(p) => p,
            None => QueryParameters {
                named: Some(
                    self.params
                        .lock()
                        .expect("Failed to lock params mutex")
                        .iter()
                        .map(|(key, value)| (key.clone(), QueryValue::Text(value.clone())))
                        .collect::<HashMap<String, QueryValue>>(),
                ),
                positional: None,
            },
        };

        if let Some(params) = params.get_named() {
            let query_params = QueryParameters {
                named: Some(params.clone()),
                positional: None,
            };
            self.bind_named(query_params)?;
        }
    
        if let Some(params) = params.get_positional() {
            let query_params = QueryParameters {
                positional: Some(params.clone()),
                named: None,
            };
            self.bind_positional(query_params)?;
        }

        let result = LibSQLResult::__construct(self.conn_id.clone(), self.stmt.as_str(), Some(params))?;

        // Clear the params after execution
        self.params.lock().unwrap().clear();

        Ok(result)
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

        self.params.lock().unwrap().clear();
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
