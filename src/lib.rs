#![allow(non_snake_case, deprecated)]
#![cfg_attr(windows, feature(abi_vectorcall))]
#[macro_use]
extern crate lazy_static;
extern crate ext_php_rs;
use ext_php_rs::{
    prelude::*,
    types::{ZendHashTable, Zval},
};
use libsql::{params::Params, version, version_number};
use std::{collections::HashMap, sync::Mutex};
use tokio::runtime::Runtime;

lazy_static! {
    static ref RT: Runtime = tokio::runtime::Runtime::new().unwrap();
}

lazy_static::lazy_static! {
    static ref CONNECTION_REGISTRY: Mutex<HashMap<String, libsql::Connection>> = Mutex::new(HashMap::new());
}

fn convert_libsql_value_to_zval(value: libsql::Value) -> Result<Zval, ext_php_rs::error::Error> {
    match value {
        libsql::Value::Integer(i) => Ok(ext_php_rs::convert::IntoZval::into_zval(i, false)?),
        libsql::Value::Real(f) => Ok(ext_php_rs::convert::IntoZval::into_zval(f, false)?),
        libsql::Value::Text(s) => Ok(ext_php_rs::convert::IntoZval::into_zval(s, false)?),
        libsql::Value::Blob(b) => {
            Ok(ext_php_rs::convert::IntoZval::into_zval(b, false)?)
        }
        libsql::Value::Null => Ok(Zval::new()),
    }
}

fn convert_vec_hashmap_to_php_array(
    vec: Vec<HashMap<String, libsql::Value>>,
) -> Result<Zval, ext_php_rs::error::Error> {
    let mut outer_array = ZendHashTable::new();

    for hashmap in vec {
        let mut inner_array = ZendHashTable::new();

        for (key, value) in hashmap {
            let php_value = convert_libsql_value_to_zval(value)?;
            inner_array.insert(&key, php_value)?;
        }

        let inner_array_zval = ext_php_rs::convert::IntoZval::into_zval(inner_array, false)?;
        outer_array.push(inner_array_zval)?;
    }

    ext_php_rs::convert::IntoZval::into_zval(outer_array, false)
}

#[php_class]
struct LibSQLPHP {
    #[prop]
    mode: String,
    #[prop]
    conn_id: String,
}

#[php_impl]
impl LibSQLPHP {
    pub fn __construct(config: HashMap<String, String>) -> Result<Self, PhpException> {
        // Extract the "url" value from the config HashMap
        let bind_url = "default:url".to_string();
        let bind_flags = "default:flags".to_string();
        let bind_auth_token = "default:authToken".to_string();
        let bind_sync_url = "default:syncUrl".to_string();
        let bind_sync_interval = "default:syncInterval".to_string();
        let bind_encryption_key = "default:encryptionKey".to_string();

        let url = config.get("url").cloned().unwrap_or(bind_url);
        let _flags = config.get("flags").cloned().unwrap_or(bind_flags);
        let auth_token = config.get("authToken").cloned().unwrap_or(bind_auth_token);
        let sync_url = config.get("syncUrl").cloned().unwrap_or(bind_sync_url);
        let _sync_interval = config
            .get("syncInterval")
            .cloned()
            .unwrap_or(bind_sync_interval);
        let _encryption_key = config
            .get("syncInterval")
            .cloned()
            .unwrap_or(bind_encryption_key);

        let mode = match (Some(&url), Some(&auth_token), Some(&sync_url)) {
            (Some(url), Some(_), Some(sync_url))
                if url.starts_with("file:")
                    && (sync_url.starts_with("libsql://")
                        || sync_url.starts_with("http://")
                        || sync_url.starts_with("https://")) =>
            {
                "remote_replica".to_string()
            }
            (Some(url), Some(_), _)
                if url.starts_with("libsql://")
                    || url.starts_with("http://")
                    || url.starts_with("https://") =>
            {
                "remote".to_string()
            }
            (Some(url), _, _) if url.starts_with("file:") || url.contains(":memory:") => {
                "local".to_string()
            }
            _ => panic!("Invalid configuration!"),
        };

        let conn = match mode.as_str() {
            "local" => {
                let conn = RT.block_on(async {
                    let db = libsql::Builder::new_local(url).build().await.unwrap();
                    let conn = db.connect().unwrap();
                    conn
                });

                conn
            }
            "remote" => {
                let conn = RT.block_on(async {
                    let db = libsql::Builder::new_remote(url, auth_token)
                        .build()
                        .await
                        .unwrap();
                    let conn = db.connect().unwrap();
                    conn
                });

                conn
            }
            _ => return Err(PhpException::default("Invalid configuration!".into())),
        };

        let conn_id = uuid::Uuid::new_v4().to_string();
        CONNECTION_REGISTRY
            .lock()
            .unwrap()
            .insert(conn_id.clone(), conn);

        Ok(Self { mode, conn_id })
    }

    pub fn version() -> String {
        let lisql_version =
            "LibSQL version ".to_string() + ": " + &version() + "-" + &version_number().to_string();
        lisql_version
    }

    pub fn changes(&self) -> Result<u64, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
        let conn = conn_registry
            .get(&self.conn_id)
            .ok_or_else(|| PhpException::from("Connection not found"))?;

        let affected_rows = RT.block_on(async { conn.changes() });
        Ok(affected_rows)
    }

    pub fn is_autocommit(&self) -> Result<bool, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
        let conn = conn_registry
            .get(&self.conn_id)
            .ok_or_else(|| PhpException::from("Connection not found"))?;

        let is_autocommit = RT.block_on(async { conn.is_autocommit() });
        if is_autocommit {
            Ok(true)
        } else {
            Ok(false)
        }
    }

    pub fn exec(&self, stmt: &str) -> Result<bool, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
        let conn = conn_registry
            .get(&self.conn_id)
            .ok_or_else(|| PhpException::from("Connection not found"))?;

        let result = RT.block_on(async { conn.execute(stmt, ()).await });
        match result {
            Ok(_) => Ok(true),
            Err(e) => Err(PhpException::from(e.to_string())),
        }
    }

    pub fn query(
        &self,
        stmt: &str,
        positional: Option<Vec<String>>,
        named: Option<HashMap<String, String>>,
    ) -> Result<Zval, PhpException> {
        let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
        let conn = conn_registry
            .get(&self.conn_id)
            .ok_or_else(|| PhpException::from("Connection not found"))?;

        let positional_params = positional
            .unwrap_or_else(Vec::new)
            .into_iter()
            .map(|s| { 
                let value = if let Ok(int_val) = s.parse::<i64>() {
                    libsql::Value::Integer(int_val)
                } else if let Ok(float_val) = s.parse::<f64>() {
                    libsql::Value::Real(float_val)
                } else if s.to_lowercase() == "null" {
                    libsql::Value::Null
                } else if let Ok(blob_val) = base64::decode(&s) {
                    libsql::Value::Blob(blob_val)
                } else {
                    libsql::Value::Text(s)
                };
                value
            })
            .collect::<Vec<libsql::Value>>();

        let param_named = named
            .map(|params| {
                params
                    .into_iter()
                    .map(|(k, v)| {
                        let value = if let Ok(int_val) = v.parse::<i64>() {
                            libsql::Value::Integer(int_val)
                        } else if let Ok(float_val) = v.parse::<f64>() {
                            libsql::Value::Real(float_val)
                        } else if v.to_lowercase() == "null" {
                            libsql::Value::Null
                        } else if let Ok(blob_val) = base64::decode(&v) {
                            libsql::Value::Blob(blob_val)
                        } else {
                            libsql::Value::Text(v)
                        };
                        (k, value)
                    })
                    .collect::<Vec<(String, libsql::Value)>>()
            })
            .unwrap_or_else(Vec::new);

        let parameters = match (positional_params.is_empty(), param_named.is_empty()) {
            (false, true) => Params::Positional(positional_params),
            (true, false) => Params::Named(param_named),
            _ => Params::None,
        };

        let query_result = RT.block_on(async {
            match conn.query(stmt, parameters).await {
                Ok(mut rows) => {
                    let mut results: Vec<HashMap<String, libsql::Value>> = Vec::new();
                    while let Ok(Some(row)) = rows.next().await {
                        let mut result = HashMap::new();
                        for idx in 0..rows.column_count() {
                            let column_name = row.column_name(idx as i32).unwrap();
                            let value = row.get_value(idx).unwrap();
                            result.insert(column_name.to_string(), value);
                        }
                        results.push(result);
                    }
                    Ok(results)
                }
                Err(e) => Err(e),
            }
        });

        match query_result {
            Ok(results) => convert_vec_hashmap_to_php_array(results)
                .map_err(|e| PhpException::from(e.to_string())),
            Err(e) => Err(PhpException::from(e.to_string())),
        }
    }
}

#[php_module]
pub fn get_module(module: ModuleBuilder) -> ModuleBuilder {
    module
}
