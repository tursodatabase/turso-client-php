use ext_php_rs::exception::PhpException;

use crate::{utils::runtime::runtime, CONNECTION_REGISTRY};

pub fn get_changes(conn_id: String) -> Result<u64, PhpException> {
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

    let conn = conn_registry
        .get(&conn_id)
        .ok_or_else(|| PhpException::from("Connection not found"))?;

    let affected_rows = runtime().block_on(async { conn.changes() });

    Ok(affected_rows)
}
