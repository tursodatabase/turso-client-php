#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::convert::IntoZval;

use ext_php_rs::types::Zval;
use std::collections::HashMap;
use ext_php_rs::prelude::*;
use ext_php_rs::{php_class, php_impl};

use crate::{
    generator::LibSQLIterator,
    hooks,
    utils::{
        query_params::QueryParameters,
        runtime::{convert_libsql_value_to_zval, runtime},
    },
    CONNECTION_REGISTRY, LIBSQL_ALL, LIBSQL_ASSOC, LIBSQL_LAZY, LIBSQL_NUM,
};

pub enum FetchResult {
    Zval(ext_php_rs::types::Zval),
    Iterator(LibSQLIterator),
}

impl IntoZval for FetchResult {
    fn set_zval(self, zv: &mut ext_php_rs::types::Zval, _: bool) -> ext_php_rs::error::Result<()> {
        match self {
            FetchResult::Zval(zval) => zval.set_zval(zv, false),
            FetchResult::Iterator(iterator) => iterator.set_zval(zv, false),
        }
    }

    const TYPE: ext_php_rs::flags::DataType = ext_php_rs::flags::DataType::Mixed; // You need to specify the correct DataType here

    fn into_zval(self, persistent: bool) -> ext_php_rs::error::Result<ext_php_rs::types::Zval> {
        let mut zval = ext_php_rs::types::Zval::new();
        self.set_zval(&mut zval, persistent)?;
        Ok(zval)
    }
}

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

    pub fn fetch_array(&self, mode: Option<i32>) -> Result<FetchResult, PhpException> {
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

                    let zval_arr = if mode == LIBSQL_LAZY {
                        let data = arr.into_zval(false).unwrap();
                        FetchResult::Iterator(LibSQLIterator::__construct(&data))
                    } else {
                        FetchResult::Zval(arr.into_zval(false).unwrap())
                    };
                    Ok(zval_arr)
                }
                Err(e) => Err(e),
            }
        } else {
            Ok(FetchResult::Zval(hooks::use_query::query(
                self.conn_string.clone(),
                self.sql.as_str(),
                self.query_params.clone(),
            )?))
        }
    }

    pub fn fetch_single(&self, mode: Option<i32>) -> Result<FetchResult, PhpException> {
        let mode = mode.unwrap_or(3);

        if mode != LIBSQL_ALL {
            let query_result = runtime().block_on(async {
                let mut rows = self
                    .conn
                    .query(self.sql.as_str(), self.parameters.clone())
                    .await
                    .map_err(|e| PhpException::from(e.to_string()))?;
                let mut result = HashMap::new();

                if let Ok(Some(row)) = rows.next().await {
                    match mode {
                        LIBSQL_ASSOC => {
                            for idx in 0..rows.column_count() {
                                let column_name = row.column_name(idx as i32).unwrap();
                                result.insert(column_name.to_string(), row.get_value(idx).unwrap());
                            }
                        }
                        LIBSQL_NUM => {
                            for idx in 0..rows.column_count() {
                                result.insert(idx.to_string(), row.get_value(idx).unwrap());
                            }
                        }
                        _ => {
                            for idx in 0..rows.column_count() {
                                let column_name = row.column_name(idx as i32).unwrap();
                                let value = row.get_value(idx).unwrap();
                                result.insert(column_name.to_string(), value.clone());
                                result.insert(idx.to_string(), value);
                            }
                        }
                    }
                }

                Ok(result)
            });

            match query_result {
                Ok(result) => {
                    let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
                    for (key, value) in result {
                        let zval_value = convert_libsql_value_to_zval(value);
                        match key.parse::<usize>() {
                            Ok(_) => sub_arr.push(zval_value)?,
                            Err(_) => sub_arr.insert(&key, zval_value)?,
                        }
                    }

                    let fetch_result = if mode == LIBSQL_LAZY {
                        let data = sub_arr.into_zval(false)?;
                        FetchResult::Iterator(LibSQLIterator::__construct(&data))
                    } else {
                        FetchResult::Zval(sub_arr.into_zval(false)?)
                    };
                    Ok(fetch_result)
                }
                Err(e) => Err(e),
            }
        } else {
            let result_set = hooks::use_query::query(
                self.conn_string.clone(),
                self.sql.as_str(),
                self.query_params.clone(),
            )?;

            let arr = result_set
                .array()
                .ok_or_else(|| PhpException::from("Result set is not an array"))?;

            let rows = arr.get("rows")
                .and_then(|zv| zv.array())
                .ok_or_else(|| PhpException::from("Missing rows in result set"))?;

            let first_row = rows.get_index(0)
                .map(|zv| {
                    let z = Zval::new();
                    unsafe {
                        std::ptr::copy_nonoverlapping(
                            zv.value.counted,
                            z.value.counted,
                            1
                        );
                    }
                    z
                })
                .unwrap_or_else(Zval::new);

            Ok(FetchResult::Zval(first_row))
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
                let column_name = row.column_name(column_index).ok_or_else(|| {
                    PhpException::from(format!("Column index {} out of bounds", column_index))
                })?;
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
                let column_type = row
                    .column_type(column_index)
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
                Err(PhpException::from(
                    "No rows returned from query, unable to determine number of columns",
                ))
            }
        })
    }

    pub fn finalize(&self) -> Result<(), PhpException> {
        // This function handle by libsql crate by default
        Ok(())
    }

    pub fn reset(&self) -> Result<(), PhpException> {
        runtime().block_on(async { self.conn.reset().await });

        Ok(())
    }
}
