use crate::utils::runtime::runtime;

/// Creates a connection to a remote replica database.
///
/// # Arguments
///
/// * `url` - The URL of the remote replica database.
/// * `auth_token` - The authentication token for accessing the remote replica.
/// * `sync_url` - The URL for synchronization with the remote replica.
/// * `sync_interval` - The synchronization interval.
/// * `read_your_writes` - A boolean indicating whether "read your writes" mode is enabled.
/// * `encryption_key` - Optional encryption key for the database.
///
/// # Returns
///
/// Returns a `libsql::Connection` representing the connection to the remote replica database.
pub fn create_remote_replica_connection(
    url: String,
    auth_token: String,
    sync_url: String,
    sync_interval: std::time::Duration,
    read_your_writes: bool,
    encryption_key: Option<String>,
) -> libsql::Connection {
    let conn = runtime().block_on(async {
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
        conn
    });

    conn
}
