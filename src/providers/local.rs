use ext_php_rs::prelude::PhpException;

use crate::utils::runtime::runtime;

/// Creates a local database connection.
///
/// # Arguments
///
/// * `url` - The URL of the local database.
/// * `flags` - Optional flags for opening the database. The PHP constants
///   (`OPEN_READONLY` = 1, `OPEN_READWRITE` = 2, `OPEN_CREATE` = 4) map onto
///   the SQLite open flags, so combinations such as `5`
///   (READ_ONLY | CREATE) or `7` (all three) take effect as combinations.
/// * `encryption_key` - Optional encryption key for the database.
///
/// # Returns
///
/// Returns a `libsql::Connection` representing the connection to the local database.
pub fn create_local_connection(
    url: String,
    flags: Option<i32>,
    encryption_key: Option<String>,
) -> Result<libsql::Connection, PhpException> {
    runtime().block_on(async {
        let db_flags = match flags {
            Some(raw_flags) => {
                // The extension's flag constants match the SQLite bit values
                // exactly, so the raw bitmask converts directly. Unknown bits
                // are ignored. An empty mask (e.g. a literal `0`) falls back
                // to the default READ_WRITE | CREATE, matching SQLite's
                // requirement that at least one access flag is set.
                let parsed = libsql::OpenFlags::from_bits_truncate(raw_flags as std::ffi::c_int);
                if parsed.is_empty() {
                    libsql::OpenFlags::default()
                } else {
                    parsed
                }
            }
            None => libsql::OpenFlags::default(),
        };

        let encryption_config = if let Some(key) = encryption_key {
            Some(libsql::EncryptionConfig::new(
                libsql::Cipher::Aes256Cbc,
                key.as_bytes().to_vec().into(),
            ))
        } else {
            None
        };

        let builder = libsql::Builder::new_local(url).flags(db_flags);

        let db = match encryption_config {
            Some(config) => builder.encryption_config(config),
            None => builder,
        }
        .build()
        .await
        .map_err(|e| PhpException::default(format!("Database build failed: {}", e)))?;

        db.connect()
            .map_err(|e| PhpException::default(format!("Connection failed: {}", e)))
    })
}
