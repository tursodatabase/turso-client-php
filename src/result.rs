#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::convert::IntoZval;

use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use ext_php_rs::{php_class, php_impl};
use std::collections::HashMap;

use crate::{
    generator::LibSQLIterator,
    hooks,
    utils::{
        query_params::QueryParameters,
        runtime::{convert_libsql_value_to_zval, runtime},
    },
    CONNECTION_REGISTRY, LIBSQL_ALL, LIBSQL_ASSOC, LIBSQL_LAZY, LIBSQL_NUM,
    OFFLINE_CONNECTION_REGISTRY,
};

pub enum FetchResult {
    Zval(ext_php_rs::types::Zval),
    Iterator(LibSQLIterator),
}

impl IntoZval for FetchResult {
    const TYPE: ext_php_rs::flags::DataType = ext_php_rs::flags::DataType::Mixed;
    const NULLABLE: bool = false;

    fn set_zval(self, zv: &mut ext_php_rs::types::Zval, _: bool) -> ext_php_rs::error::Result<()> {
        match self {
            FetchResult::Zval(zval) => zval.set_zval(zv, false),
            FetchResult::Iterator(iterator) => iterator.set_zval(zv, false),
        }
    }

    fn into_zval(self, persistent: bool) -> ext_php_rs::error::Result<ext_php_rs::types::Zval> {
        let mut zval = ext_php_rs::types::Zval::new();
        self.set_zval(&mut zval, persistent)?;
        Ok(zval)
    }
}

#[php_class]
pub struct LibSQLResult {
    pub conn_string: String,
    pub conn: Option<libsql::Connection>,
    pub sql: String,
    pub parameters: libsql::params::Params,
    pub query_params: Option<QueryParameters>,
    pub force_remote: Option<bool>,
    pub is_offline_mode: bool,
    pub sqld_offline_mode: bool,
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
            conn: Some(conn.clone()),
            sql: sql.to_string(),
            parameters: params,
            query_params: parameters,
            force_remote: None,
            is_offline_mode: false,
            sqld_offline_mode: false,
        })
    }

    /// Constructor for offline write mode
    pub fn __construct_offline(
        conn_id: String,
        sql: &str,
        parameters: Option<QueryParameters>,
        force_remote: Option<bool>,
    ) -> Result<Self, PhpException> {
        let params = if let Some(ref p) = parameters {
            let params = p.to_params();
            params
        } else {
            libsql::params::Params::None
        };

        Ok(Self {
            conn_string: conn_id,
            conn: None, // We don't store the connection directly for offline mode
            sql: sql.to_string(),
            parameters: params,
            query_params: parameters,
            force_remote: Some(force_remote.unwrap_or(false)),
            is_offline_mode: true,
            sqld_offline_mode: true,
        })
    }

    pub fn fetch_array(&self, mode: Option<i32>) -> Result<FetchResult, PhpException> {
        let mode = mode.unwrap_or(3);

        if self.is_offline_mode {
            return self.fetch_array_offline(mode);
        }

        if mode != LIBSQL_ALL {
            let conn = self
                .conn
                .as_ref()
                .ok_or_else(|| PhpException::from("Connection not available"))?;

            let query_result = runtime().block_on(async {
                let mut rows = conn
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
                                Err(_) => sub_arr.insert(key.as_str(), zval_value)?,
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

    /// Fetch array method for offline mode
    fn fetch_array_offline(&self, mode: i32) -> Result<FetchResult, PhpException> {
        let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
        let offline_conn = offline_registry
            .get(&self.conn_string)
            .ok_or_else(|| PhpException::from("Offline connection not found"))?;

        let query_result = match offline_conn.query(
            self.sql.as_str(),
            self.query_params.clone(),
            self.force_remote.clone(),
        ) {
            Ok(mut rows) => {
                let mut results: Vec<HashMap<String, libsql::Value>> = Vec::new();

                runtime().block_on(async {
                    while let Ok(Some(row)) = rows.next().await {
                        let mut result = HashMap::new();

                        if mode == LIBSQL_ASSOC {
                            for idx in 0..rows.column_count() {
                                let column_name = row.column_name(idx as i32).unwrap();
                                let value = row.get_value(idx).unwrap();
                                result.insert(column_name.to_string(), value);
                            }
                        } else if mode == LIBSQL_NUM {
                            for idx in 0..rows.column_count() {
                                let value = row.get_value(idx).unwrap();
                                result.insert(idx.to_string(), value);
                            }
                        } else {
                            for idx in 0..rows.column_count() {
                                let column_name = row.column_name(idx as i32).unwrap();
                                let value = row.get_value(idx).unwrap();
                                result.insert(column_name.to_string(), value.clone());
                                result.insert(idx.to_string(), value);
                            }
                        }
                        results.push(result);
                    }
                });

                Ok(results)
            }
            Err(e) => Err(PhpException::from(e.to_string())),
        };

        match query_result {
            Ok(results) => {
                let mut arr = ext_php_rs::types::ZendHashTable::new();

                for result in results {
                    let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
                    for (key, value) in result {
                        let zval_value = convert_libsql_value_to_zval(value);
                        match key.parse::<i64>() {
                            Ok(_) => sub_arr.push(zval_value)?,
                            Err(_) => sub_arr.insert(key.as_str(), zval_value)?,
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
    }

    pub fn fetch_single(&self, mode: Option<i32>) -> Result<FetchResult, PhpException> {
        let mode = mode.unwrap_or(3);

        if self.is_offline_mode {
            return self.fetch_single_offline(mode);
        }

        if mode != LIBSQL_ALL {
            let conn = self
                .conn
                .as_ref()
                .ok_or_else(|| PhpException::from("Connection not available"))?;

            let query_result = runtime().block_on(async {
                let mut rows = conn
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
                            Err(_) => sub_arr.insert(key.as_str(), zval_value)?,
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

            let rows = arr
                .get("rows")
                .and_then(|zv| zv.array())
                .ok_or_else(|| PhpException::from("Missing rows in result set"))?;

            let first_row = rows
                .get_index(0)
                .map(|zv| {
                    let z = Zval::new();
                    unsafe {
                        std::ptr::copy_nonoverlapping(zv.value.counted, z.value.counted, 1);
                    }
                    z
                })
                .unwrap_or_else(Zval::new);

            Ok(FetchResult::Zval(first_row))
        }
    }

    /// Fetch single method for offline mode
    fn fetch_single_offline(&self, mode: i32) -> Result<FetchResult, PhpException> {
        let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
        let offline_conn = offline_registry
            .get(&self.conn_string)
            .ok_or_else(|| PhpException::from("Offline connection not found"))?;

        let query_result = match offline_conn.query(
            self.sql.as_str(),
            self.query_params.clone(),
            self.force_remote.clone(),
        ) {
            Ok(mut rows) => {
                let mut result = HashMap::new();

                runtime().block_on(async {
                    if let Ok(Some(row)) = rows.next().await {
                        match mode {
                            LIBSQL_ASSOC => {
                                for idx in 0..rows.column_count() {
                                    let column_name = row.column_name(idx as i32).unwrap();
                                    result.insert(
                                        column_name.to_string(),
                                        row.get_value(idx).unwrap(),
                                    );
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
                });

                Ok(result)
            }
            Err(e) => Err(PhpException::from(e.to_string())),
        };

        match query_result {
            Ok(result) => {
                let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
                for (key, value) in result {
                    let zval_value = convert_libsql_value_to_zval(value);
                    match key.parse::<usize>() {
                        Ok(_) => sub_arr.push(zval_value)?,
                        Err(_) => sub_arr.insert(key.as_str(), zval_value)?,
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
    }

    pub fn column_name(&self, column_index: i32) -> Result<String, PhpException> {
        if self.is_offline_mode {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_string)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            match offline_conn.query(
                self.sql.as_str(),
                self.query_params.clone(),
                self.force_remote.clone(),
            ) {
                Ok(mut rows) => runtime().block_on(async {
                    if let Ok(Some(row)) = rows.next().await {
                        let column_name = row.column_name(column_index).ok_or_else(|| {
                            PhpException::from(format!(
                                "Column index {} out of bounds",
                                column_index
                            ))
                        })?;
                        Ok(column_name.to_string())
                    } else {
                        Err(PhpException::from("No rows returned from query"))
                    }
                }),
                Err(e) => Err(PhpException::from(e.to_string())),
            }
        } else {
            let conn = self
                .conn
                .as_ref()
                .ok_or_else(|| PhpException::from("Connection not available"))?;

            runtime().block_on(async {
                let mut rows = conn
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
    }

    pub fn reset(&self) -> Result<(), PhpException> {
        if self.is_offline_mode {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_string)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            runtime().block_on(async { offline_conn.reset().await });
            Ok(())
        } else {
            let conn = self
                .conn
                .as_ref()
                .ok_or_else(|| PhpException::from("Connection not available"))?;

            runtime().block_on(async { conn.reset().await });
            Ok(())
        }
    }
}
