use crate::utils::runtime::runtime;

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
