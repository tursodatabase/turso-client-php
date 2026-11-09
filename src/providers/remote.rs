use ext_php_rs::prelude::PhpException;

use crate::utils::runtime::runtime;

/// Creates a connection to a remote database.
///
/// # Arguments
///
/// * `url` - The URL of the remote database.
/// * `auth_token` - The authentication token for accessing the remote database.
///
/// # Returns
///
/// Returns a `Result` containing a `libsql::Connection` representing the
/// connection to the remote database, or a `PhpException` if the database
/// could not be built or connected to.
///
/// # Errors
///
/// Returns a `PhpException` if the remote database fails to build or the
/// connection cannot be established.
pub fn create_remote_connection(
    url: String,
    auth_token: String,
) -> Result<libsql::Connection, PhpException> {
    runtime().block_on(async {
        let db = libsql::Builder::new_remote(url, auth_token)
            .build()
            .await
            .map_err(|e| PhpException::default(format!("Remote database build failed: {}", e)))?;

        db.connect()
            .map_err(|e| PhpException::default(format!("Remote connection failed: {}", e)))
    })
}
