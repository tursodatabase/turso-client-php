use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

pub fn get_is_autocommit(conn_id: String) -> Result<bool, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let is_autocommit = runtime().block_on(async { conn.is_autocommit() });
    if is_autocommit {
        Ok(true)
    } else {
        Ok(false)
    }
}
