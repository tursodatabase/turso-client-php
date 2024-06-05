#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use crate::ext_php_rs::convert::IntoZval;
extern crate ext_php_rs;

use std::collections::HashMap;

use ext_php_rs::prelude::*;

use crate::{
    hooks, utils::{
        query_params::QueryParameters,
        runtime::{convert_libsql_value_to_zval, runtime},
    }, CONNECTION_REGISTRY, LIBSQL_ALL, LIBSQL_ASSOC, LIBSQL_NUM
};

#[php_class]
pub struct LibSQLResult {
    pub conn_string: String,
    pub conn: libsql::Connection,
    pub sql: String,
    pub parameters: libsql::params::Params,
    pub query_params: Option<QueryParameters>,
}

#[php_impl]
impl LibSQLResult {
    pub fn __construct(
        conn_id: String,
        sql: &str,
        parameters: Option<QueryParameters>,
    ) -> Result<Self, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
        let conn = conn_registry
            .get(&conn_id.clone())
            .ok_or_else(|| PhpException::from("Connection not found"))?;
        let params = if let Some(ref p) = parameters {
            let params = p.to_params();
            params
        } else {
            libsql::params::Params::None
        };

        Ok(Self {
            conn_string: conn_id,
            conn: conn.clone(),
            sql: sql.to_string(),
            parameters: params,
            query_params: parameters,
        })
    }

    pub fn fetch_array(&self, mode: Option<i32>) -> Result<ext_php_rs::types::Zval, PhpException> {
        let mode = mode.unwrap_or(3);
        
        if mode != LIBSQL_ALL {
            let query_result = runtime().block_on(async {
                let mut rows = self
                    .conn
                    .query(self.sql.as_str(), self.parameters.clone())
                    .await
                    .map_err(|e| PhpException::from(e.to_string()))?;
                let mut results: Vec<HashMap<String, libsql::Value>> = Vec::new();
    
                while let Ok(Some(row)) = rows.next().await {
                    let mut result = HashMap::new();
    
                    if mode == LIBSQL_ASSOC {
                        for idx in 0..rows.column_count() {
                            let column_name = row.column_name(idx as i32).unwrap();
                            let value = row.get_value(idx).unwrap();
    
                            result.insert(column_name.to_string(), value);
                        }
                        results.push(result);
                    } else if mode == LIBSQL_NUM {
                        for idx in 0..rows.column_count() {
                            let value = row.get_value(idx).unwrap();
    
                            result.insert(idx.to_string(), value);
                        }
                        results.push(result);
                    } else {
                        for idx in 0..rows.column_count() {
                            let column_name = row.column_name(idx as i32).unwrap();
                            let value = row.get_value(idx).unwrap();
    
                            result.insert(column_name.to_string(), value.clone());
                            result.insert(idx.to_string(), value);
                        }
                        results.push(result);
                    }
                }
    
                Ok(results)
            });
    
            match query_result {
                Ok(results) => {
                    let mut arr = ext_php_rs::types::ZendHashTable::new();
    
                    for result in results {
                        let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
                        for (key, value) in result {
                            let zval_value = convert_libsql_value_to_zval(value);
                            match key.parse::<i64>() {
                                Ok(_) => sub_arr.push(zval_value)?,
                                Err(_) => sub_arr.insert(&key, zval_value)?,
                            }
                        }
                        arr.push(sub_arr)?;
                    }
    
                    let zval_arr = arr.into_zval(false)?;
                    Ok(zval_arr)
                }
                Err(e) => Err(e),
            }
        } else {
            hooks::use_query::query(self.conn_string.clone(), self.sql.as_str(), self.query_params.clone())
        }
    }

    pub fn column_name(&self, column_index: i32) -> Result<String, PhpException> {
        runtime().block_on(async {
            let mut rows = self
                .conn
                .query(self.sql.as_str(), self.parameters.clone())
                .await
                .map_err(|e| PhpException::from(e.to_string()))?;

            if let Ok(Some(row)) = rows.next().await {
                let column_name = row.column_name(column_index)
                    .ok_or_else(|| PhpException::from(format!("Column index {} out of bounds", column_index)))?;
                Ok(column_name.to_string())
            } else {
                Err(PhpException::from("No rows returned from query"))
            }
        })
    }

    pub fn column_type(&self, column_index: i32) -> Result<String, PhpException> {
        runtime().block_on(async {
            let mut rows = self
                .conn
                .query(self.sql.as_str(), self.parameters.clone())
                .await
                .map_err(|e| PhpException::from(e.to_string()))?;

            if let Ok(Some(row)) = rows.next().await {
                let column_type = row.column_type(column_index)
                    .map_err(|e| PhpException::from(e.to_string()))?;
                
                let column_type_str = match column_type {
                    libsql::ValueType::Integer => "Integer",
                    libsql::ValueType::Real => "Real",
                    libsql::ValueType::Text => "Text",
                    libsql::ValueType::Blob => "Blob",
                    libsql::ValueType::Null => "Null",
                };

                Ok(column_type_str.to_string())
            } else {
                Err(PhpException::from("No rows returned from query"))
            }
        })
    }

    pub fn num_columns(&self) -> Result<i32, PhpException> {
        runtime().block_on(async {
            let mut rows = self
                .conn
                .query(self.sql.as_str(), self.parameters.clone())
                .await
                .map_err(|e| PhpException::from(e.to_string()))?;

            if let Ok(Some(_)) = rows.next().await {
                let num_columns = rows.column_count();
                Ok(num_columns as i32)
            } else {
                Err(PhpException::from("No rows returned from query, unable to determine number of columns"))
            }
        })
    }

    pub fn finalize(&self) -> Result<(), PhpException> {
        // This function handle by libsql crate by default
        Ok(())
    }

    pub fn reset(&self) -> Result<(), PhpException> {
        runtime().block_on(async {
            self.conn.reset().await
        });

        Ok(())
    }
}
