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
/// A tuple containing:
/// - A `libsql::Database` object representing the database.
/// - A `libsql::Connection` object representing the connection.
///
/// # Panics
///
/// This function will panic if:
/// - The database fails to build.
/// - The connection to the database cannot be established.
///
/// # Examples
///
/// ```
/// let (db, conn) = create_remote_replica_connection(
///     "https://example.com/db".to_string(),
///     "auth_token".to_string(),
///     "https://example.com/sync".to_string(),
///     std::time::Duration::from_secs(5),
///     true,
///     Some("encryption_key".to_string()),
/// );
/// ```
pub fn create_remote_replica_connection(
    url: String,
    auth_token: String,
    sync_url: String,
    sync_interval: std::time::Duration,
    read_your_writes: bool,
    encryption_key: Option<String>,
) -> (libsql::Database, libsql::Connection) {
    let (db, conn) = runtime().block_on(async {
        let encryption_config = if let Some(key) = encryption_key {
            Some(libsql::EncryptionConfig::new(
                libsql::Cipher::Aes256Cbc,
                key.as_bytes().to_vec().into(),
            ))
        } else {
            None
        };

        let db = libsql::Builder::new_remote_replica(url, sync_url, auth_token)
            .encryption_config(encryption_config.unwrap())
            .read_your_writes(read_your_writes)
            .sync_interval(sync_interval)
            .build()
            .await
            .unwrap();
        let conn = db.connect().unwrap();
        (db, conn)
    });

    (db, conn)
}
