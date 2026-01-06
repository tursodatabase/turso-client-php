use std::path::Path;

use crate::CONNECTION_REGISTRY;
use ext_php_rs::prelude::PhpException;

#[derive(Debug, Clone)]
pub enum ExtensionParams {
    String(String),
    Array(Vec<String>),
}

impl<'a> ext_php_rs::convert::FromZval<'a> for ExtensionParams {
    const TYPE: ext_php_rs::flags::DataType = ext_php_rs::flags::DataType::Mixed;

    fn from_zval(zval: &'a ext_php_rs::types::Zval) -> Option<Self> {
        if let Some(s) = zval.string() {
            Some(ExtensionParams::String(s))
        } else if let Some(array) = zval.array() {
            let mut vec: Vec<String> = Vec::new();
            for (_, val) in array.iter() {
                if let Some(s) = val.string() {
                    vec.push(s);
                }
            }
            if !vec.is_empty() {
                Some(ExtensionParams::Array(vec))
            } else {
                None
            }
        } else {
            None
        }
    }
}

pub fn enable_load_extension(conn_id: String, onoff: Option<bool>) -> Result<(), PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    if Some(onoff.unwrap_or(false)).is_some() {
        conn.load_extension_enable().unwrap();
    } else {
        conn.load_extension_disable().unwrap();
    }

    Ok(())
}

pub fn load_extension(
    conn_id: String,
    dylib_path: &Path,
    entry_point: Option<&str>,
) -> Result<(), PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    conn.load_extension(dylib_path, entry_point).unwrap();
    Ok(())
}
