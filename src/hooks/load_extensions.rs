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
    let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
        PhpException::default(format!("Mutex lock error: {}", e))
    })?;
    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    if onoff.unwrap_or(false) {
        conn.load_extension_enable().map_err(|e| {
            PhpException::default(format!("Failed to enable load extension: {}", e))
        })?;
    } else {
        conn.load_extension_disable().map_err(|e| {
            PhpException::default(format!("Failed to disable load extension: {}", e))
        })?;
    }

    Ok(())
}

pub fn load_extension(
    conn_id: String,
    dylib_path: &Path,
    entry_point: Option<&str>,
) -> Result<(), PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
        PhpException::default(format!("Mutex lock error: {}", e))
    })?;
    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    conn.load_extension(dylib_path, entry_point).map_err(|e| {
        PhpException::default(format!("Failed to load extension: {}", e))
    })?;
    Ok(())
}
