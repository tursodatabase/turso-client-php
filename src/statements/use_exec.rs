use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

pub fn exec(conn_id: String, stmt: &str) -> Result<bool, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let result = runtime().block_on(async { conn.execute(stmt, ()).await });
    match result {
        Ok(_) => Ok(true),
        Err(e) => Err(PhpException::from(e.to_string())),
    }
}
