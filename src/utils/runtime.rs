use std::collections::HashMap;

use ext_php_rs::types::{ZendHashTable, Zval};
use once_cell::sync::OnceCell;
use tokio::runtime::Runtime;

pub fn convert_libsql_value_to_zval(
    value: libsql::Value,
) -> Result<Zval, ext_php_rs::error::Error> {
    match value {
        libsql::Value::Integer(i) => Ok(ext_php_rs::convert::IntoZval::into_zval(i, false)?),
        libsql::Value::Real(f) => Ok(ext_php_rs::convert::IntoZval::into_zval(f, false)?),
        libsql::Value::Text(s) => Ok(ext_php_rs::convert::IntoZval::into_zval(s, false)?),
        libsql::Value::Blob(b) => Ok(ext_php_rs::convert::IntoZval::into_zval(b, false)?),
        libsql::Value::Null => Ok(Zval::new()),
    }
}

pub fn convert_vec_hashmap_to_php_array(
    vec: Vec<HashMap<String, libsql::Value>>,
) -> Result<Zval, ext_php_rs::error::Error> {
    let mut outer_array = ZendHashTable::new();

    for hashmap in vec {
        let mut inner_array = ZendHashTable::new();

        for (key, column_data) in hashmap {
            let php_value = convert_libsql_value_to_zval(column_data).unwrap();
            inner_array.insert(&key.as_str(), php_value)?;
        }

        let inner_array_zval = ext_php_rs::convert::IntoZval::into_zval(
            ext_php_rs::boxed::ZBox::from(inner_array),
            false,
        )?;
        outer_array.push(inner_array_zval)?;
    }

    ext_php_rs::convert::IntoZval::into_zval(ext_php_rs::boxed::ZBox::from(outer_array), false)
}

pub fn runtime() -> &'static Runtime {
    static RUNTIME: OnceCell<Runtime> = OnceCell::new();

    RUNTIME.get_or_try_init(Runtime::new).unwrap()
}

pub fn get_mode(
    url: Option<String>,
    auth_token: Option<String>,
    sync_url: Option<String>,
) -> String {
    match (url, auth_token, sync_url) {
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
        _ => "Mode is not available!".to_string(),
    }
}
