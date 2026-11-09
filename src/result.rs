#[allow(non_snake_case, deprecated, unused_attributes)]
#[cfg_attr(windows, feature(abi_vectorcall))]
use ext_php_rs::convert::IntoZval;

use ext_php_rs::prelude::*;
use ext_php_rs::types::Zval;
use ext_php_rs::{php_class, php_impl};

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

/// Reads the string form of a column's value type from a row set: from the
/// first row when one exists, otherwise from the row set's declared column
/// type (SQLite value types are per-row; zero-row results report the declared
/// type — Null for local databases, the server's column type for remote
/// queries).
async fn column_type_from_rows(
    rows: &mut libsql::Rows,
    column_index: i32,
) -> Result<String, PhpException> {
    match rows.next().await {
        Ok(Some(row)) => {
            let column_type = row.column_type(column_index).or_else(|_| {
                Err(PhpException::from(format!(
                    "Column index {} out of bounds",
                    column_index
                )))
            })?;
            Ok(format!("{:?}", column_type))
        }
        Ok(None) => {
            let column_type = rows.column_type(column_index).or_else(|_| {
                Err(PhpException::from(format!(
                    "Column index {} out of bounds",
                    column_index
                )))
            })?;
            Ok(format!("{:?}", column_type))
        }
        Err(e) => Err(PhpException::from(format!("Failed to fetch row: {}", e))),
    }
}

pub enum FetchResult {
    Zval(ext_php_rs::types::Zval),
    Iterator(LibSQLIterator),
}

/// Collects one result row into an ordered column/value list according to the
/// fetch mode, preserving the exact per-mode shapes of the previous manual
/// conversions. The list is built in column-index order so that numeric-keyed
/// rows (LIBSQL_NUM / LIBSQL_BOTH) materialize in the correct order — a
/// HashMap would iterate keys in arbitrary order and scramble the columns.
/// Errors propagate instead of being silently swallowed.
fn collect_row_map(
    rows: &libsql::Rows,
    row: &libsql::Row,
    mode: i32,
) -> Result<Vec<(String, libsql::Value)>, PhpException> {
    let mut result = Vec::new();

    for idx in 0..rows.column_count() {
        let idx = idx as i32;
        let value = row
            .get_value(idx)
            .map_err(|e| PhpException::from(format!("Failed to read column value: {}", e)))?;

        match mode {
            LIBSQL_ASSOC => {
                let column_name = row.column_name(idx).ok_or_else(|| {
                    PhpException::from(format!("Column index {} out of bounds", idx))
                })?;
                result.push((column_name.to_string(), value));
            }
            LIBSQL_NUM => {
                result.push((idx.to_string(), value));
            }
            // LIBSQL_BOTH (and fallback): string key plus numeric key.
            _ => {
                let column_name = row.column_name(idx).ok_or_else(|| {
                    PhpException::from(format!("Column index {} out of bounds", idx))
                })?;
                result.push((column_name.to_string(), value.clone()));
                result.push((idx.to_string(), value));
            }
        }
    }

    Ok(result)
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

            // LIBSQL_LAZY hands the raw row set to a streaming iterator so
            // rows are pulled one by one instead of being materialized up
            // front.
            if mode == LIBSQL_LAZY {
                let rows = runtime().block_on(async {
                    conn.query(self.sql.as_str(), self.parameters.clone())
                        .await
                        .map_err(|e| PhpException::from(e.to_string()))
                })?;
                return Ok(FetchResult::Iterator(LibSQLIterator::from_rows(rows)));
            }

            let query_result = runtime().block_on(async {
                let mut rows = conn
                    .query(self.sql.as_str(), self.parameters.clone())
                    .await
                    .map_err(|e| PhpException::from(e.to_string()))?;

                let mut results: Vec<Vec<(String, libsql::Value)>> = Vec::new();
                loop {
                    match rows.next().await {
                        Ok(Some(row)) => results.push(collect_row_map(&rows, &row, mode)?),
                        Ok(None) => break,
                        Err(e) => {
                            return Err(PhpException::from(format!("Failed to fetch row: {}", e)))
                        }
                    }
                }

                Ok(results)
            });

            let results = query_result?;
            let mut arr = ext_php_rs::types::ZendHashTable::new();

            for result in results {
                let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
                for (key, value) in result {
                    let zval_value = convert_libsql_value_to_zval(value)?;
                    match key.parse::<i64>() {
                        Ok(_) => sub_arr.push(zval_value)?,
                        Err(_) => sub_arr.insert(key.as_str(), zval_value)?,
                    }
                }
                arr.push(sub_arr)?;
            }

            Ok(FetchResult::Zval(arr.into_zval(false)?))
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

        // LIBSQL_LAZY hands the raw row set to a streaming iterator so rows
        // are pulled one by one instead of being materialized up front.
        if mode == LIBSQL_LAZY {
            let rows = offline_conn
                .query(
                    self.sql.as_str(),
                    self.query_params.clone(),
                    self.force_remote.clone(),
                )
                .map_err(|e| PhpException::from(e.to_string()))?;
            return Ok(FetchResult::Iterator(LibSQLIterator::from_rows(rows)));
        }

        let query_result = match offline_conn.query(
            self.sql.as_str(),
            self.query_params.clone(),
            self.force_remote.clone(),
        ) {
            Ok(mut rows) => {
                let mut results: Vec<Vec<(String, libsql::Value)>> = Vec::new();

                runtime().block_on(async {
                    loop {
                        match rows.next().await {
                            Ok(Some(row)) => results.push(collect_row_map(&rows, &row, mode)?),
                            Ok(None) => break,
                            Err(e) => {
                                return Err(PhpException::from(format!(
                                    "Failed to fetch row: {}",
                                    e
                                )))
                            }
                        }
                    }
                    Ok(())
                })?;

                Ok(results)
            }
            Err(e) => Err(PhpException::from(e.to_string())),
        };

        let results = query_result?;
        let mut arr = ext_php_rs::types::ZendHashTable::new();

        for result in results {
            let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
            for (key, value) in result {
                let zval_value = convert_libsql_value_to_zval(value)?;
                match key.parse::<i64>() {
                    Ok(_) => sub_arr.push(zval_value)?,
                    Err(_) => sub_arr.insert(key.as_str(), zval_value)?,
                }
            }
            arr.push(sub_arr)?;
        }

        Ok(FetchResult::Zval(arr.into_zval(false)?))
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
                let mut result: Vec<(String, libsql::Value)> = Vec::new();

                match rows.next().await {
                    Ok(Some(row)) => {
                        result = collect_row_map(&rows, &row, mode)?;
                    }
                    Ok(None) => {}
                    Err(e) => {
                        return Err(PhpException::from(format!("Failed to fetch row: {}", e)))
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

            // `shallow_clone` increments the refcount for refcounted values, so
            // the borrowed row zval can safely be handed back to PHP. Copying
            // the raw zval without bumping the refcount (the previous
            // implementation) could leave PHP holding a dangling pointer.
            let first_row = rows
                .get_index(0)
                .map(|zv| zv.shallow_clone())
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
                let mut result: Vec<(String, libsql::Value)> = Vec::new();

                runtime().block_on(async {
                    match rows.next().await {
                        Ok(Some(row)) => {
                            result = collect_row_map(&rows, &row, mode)?;
                        }
                        Ok(None) => {}
                        Err(e) => {
                            return Err(PhpException::from(format!("Failed to fetch row: {}", e)))
                        }
                    }
                    Ok(())
                })?;

                Ok(result)
            }
            Err(e) => Err(PhpException::from(e.to_string())),
        };

        let result = query_result?;

        let mut sub_arr = ext_php_rs::types::ZendHashTable::new();
        for (key, value) in result {
            let zval_value = convert_libsql_value_to_zval(value)?;
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
                // Column metadata is available from the row set itself — no
                // row needs to be fetched, so zero-row queries work too.
                Ok(rows) => rows
                    .column_name(column_index)
                    .map(|name| name.to_string())
                    .ok_or_else(|| {
                        PhpException::from(format!("Column index {} out of bounds", column_index))
                    }),
                Err(e) => Err(PhpException::from(e.to_string())),
            }
        } else {
            let conn = self
                .conn
                .as_ref()
                .ok_or_else(|| PhpException::from("Connection not available"))?;

            runtime().block_on(async {
                let rows = conn
                    .query(self.sql.as_str(), self.parameters.clone())
                    .await
                    .map_err(|e| PhpException::from(e.to_string()))?;

                rows.column_name(column_index)
                    .map(|name| name.to_string())
                    .ok_or_else(|| {
                        PhpException::from(format!("Column index {} out of bounds", column_index))
                    })
            })
        }
    }

    pub fn column_type(&self, column_index: i32) -> Result<String, PhpException> {
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
                Ok(mut rows) => runtime().block_on(column_type_from_rows(&mut rows, column_index)),
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

                column_type_from_rows(&mut rows, column_index).await
            })
        }
    }

    pub fn num_columns(&self) -> Result<i32, PhpException> {
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
                // The column count is available from the row set itself — no
                // row needs to be fetched, so zero-row queries work too.
                Ok(rows) => Ok(rows.column_count()),
                Err(e) => Err(PhpException::from(e.to_string())),
            }
        } else {
            let conn = self
                .conn
                .as_ref()
                .ok_or_else(|| PhpException::from("Connection not available"))?;

            runtime().block_on(async {
                let rows = conn
                    .query(self.sql.as_str(), self.parameters.clone())
                    .await
                    .map_err(|e| PhpException::from(e.to_string()))?;

                Ok(rows.column_count())
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
