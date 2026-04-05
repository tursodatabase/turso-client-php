use crate::utils::runtime::runtime;
use ext_php_rs::prelude::PhpException;

/// Creates a connection to a remote database.
///
/// # Arguments
///
/// * `url` - The URL of the remote database.
/// * `auth_token` - The authentication token for accessing the remote database.
///
/// # Returns
///
/// Returns a `Result` containing either the `libsql::Connection` or a `PhpException`
/// if the connection fails.
pub fn create_remote_connection(
    url: String,
    auth_token: String,
) -> Result<libsql::Connection, PhpException> {
    let rt = runtime()?;
    rt.block_on(async {
        let db = libsql::Builder::new_remote(url, auth_token)
            .build()
            .await
            .map_err(|e| PhpException::default(format!("Remote database build failed: {}", e)))?;

        db.connect()
            .map_err(|e| PhpException::default(format!("Remote connection failed: {}", e)))
    })
}
