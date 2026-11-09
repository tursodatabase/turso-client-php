use ext_php_rs::prelude::PhpException;

use crate::utils::runtime::runtime;

/// Creates a connection to a synced (offline-write) database for Turso Cloud.
///
/// # Arguments
///
/// * `db_path` - The file path of the local synced database.
/// * `auth_token` - The authentication token for accessing the remote database.
/// * `sync_url` - The URL used to synchronize with the remote database.
///
/// # Returns
///
/// Returns a `Result` containing a tuple with:
/// - A `libsql::Database` object representing the synced database.
/// - A `libsql::Connection` object representing the connection.
/// or a `PhpException` if the database fails to build or the connection cannot
/// be established.
///
/// # Errors
///
/// Returns a `PhpException` if:
/// - The synced database fails to build.
/// - The connection to the database cannot be established.
pub fn create_offline_write_connection(
    db_path: String,
    auth_token: String,
    sync_url: String,
) -> Result<(libsql::Database, libsql::Connection), PhpException> {
    let (db, conn) = runtime().block_on(async {
        let db = libsql::Builder::new_synced_database(db_path, sync_url, auth_token)
            .build()
            .await
            .map_err(|e| {
                PhpException::default(format!("Offline-write database build failed: {}", e))
            })?;

        let conn = db.connect().map_err(|e| {
            PhpException::default(format!("Offline-write connection failed: {}", e))
        })?;

        Ok::<_, PhpException>((db, conn))
    })?;

    Ok((db, conn))
}
