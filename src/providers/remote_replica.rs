use ext_php_rs::prelude::PhpException;

use crate::utils::runtime::runtime;

/// Creates a new remote replica connection to a libSQL database.
///
/// This function takes various configuration parameters, establishes an asynchronous connection
/// to a remote replica database using the `libsql` crate, and returns a tuple containing the
/// database and connection objects.
///
/// # Parameters
///
/// - `url`: A string representing the URL of the remote database.
/// - `auth_token`: A string representing the authentication token.
/// - `sync_url`: A string representing the URL for synchronization.
/// - `sync_interval`: A `std::time::Duration` specifying the interval for synchronization.
/// - `read_your_writes`: A boolean indicating whether to enable "read your writes" consistency.
/// - `encryption_key`: An optional string for the encryption key, used for encrypting the database.
///
/// # Returns
///
/// A `Result` containing a tuple with:
/// - A `libsql::Database` object representing the database.
/// - A `libsql::Connection` object representing the connection.
/// or a `PhpException` if the database fails to build or the connection cannot
/// be established.
///
/// # Errors
///
/// Returns a `PhpException` if:
/// - The database fails to build.
/// - The connection to the database cannot be established.
pub fn create_remote_replica_connection(
    url: String,
    auth_token: String,
    sync_url: String,
    sync_interval: std::time::Duration,
    read_your_writes: bool,
    encryption_key: Option<String>,
) -> Result<(libsql::Database, libsql::Connection), PhpException> {
    runtime().block_on(async {
        let builder = libsql::Builder::new_remote_replica(url, sync_url, auth_token);

        let builder = if let Some(key) = encryption_key {
            builder.encryption_config(libsql::EncryptionConfig::new(
                libsql::Cipher::Aes256Cbc,
                key.as_bytes().to_vec().into(),
            ))
        } else {
            builder
        };

        let db = builder
            .read_your_writes(read_your_writes)
            .sync_interval(sync_interval)
            .build()
            .await
            .map_err(|e| PhpException::default(format!("Replica database build failed: {}", e)))?;

        let conn = db
            .connect()
            .map_err(|e| PhpException::default(format!("Replica connection failed: {}", e)))?;

        Ok((db, conn))
    })
}
