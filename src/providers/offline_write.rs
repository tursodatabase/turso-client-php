use ext_php_rs::exception::PhpException;
use crate::utils::runtime::runtime;

pub fn create_offline_write_connection(
    db_path: String,
    auth_token: String,
    sync_url: String,
) -> Result<(libsql::Database, libsql::Connection), PhpException> {
    let rt = runtime()?;
    let (db, conn) = rt.block_on(async {
        let db = libsql::Builder::new_synced_database(db_path, sync_url, auth_token)
            .build()
            .await
            .map_err(|e| PhpException::default(format!("Offline write database build failed: {}", e)))?;

        let conn = db.connect()
            .map_err(|e| PhpException::default(format!("Offline write connection failed: {}", e)))?;
        Ok::<_, PhpException>((db, conn))
    })?;

    Ok((db, conn))
}
