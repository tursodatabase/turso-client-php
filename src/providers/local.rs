use crate::{
    utils::runtime::runtime, LIBSQL_OPEN_CREATE, LIBSQL_OPEN_READONLY, LIBSQL_OPEN_READWRITE,
};

pub fn create_local_connection(
    url: String,
    flags: Option<i32>,
    encryption_key: Option<String>,
) -> libsql::Connection {
    let conn = runtime().block_on(async {
        let db_flags = match flags {
            Some(LIBSQL_OPEN_READONLY) => libsql::OpenFlags::SQLITE_OPEN_READ_ONLY,
            Some(LIBSQL_OPEN_READWRITE) => libsql::OpenFlags::SQLITE_OPEN_READ_WRITE,
            Some(LIBSQL_OPEN_CREATE) => libsql::OpenFlags::SQLITE_OPEN_CREATE,
            Some(5) => {
                libsql::OpenFlags::SQLITE_OPEN_READ_ONLY | libsql::OpenFlags::SQLITE_OPEN_CREATE
            }
            _ => libsql::OpenFlags::default(),
        };

        let encryption_config = if let Some(key) = encryption_key {
            Some(libsql::EncryptionConfig::new(
                libsql::Cipher::Aes256Cbc,
                key.as_bytes().to_vec().into(),
            ))
        } else {
            None
        };

        let db = libsql::Builder::new_local(url)
            .flags(db_flags)
            .encryption_config(encryption_config.unwrap())
            .build()
            .await
            .unwrap();
        let conn = db.connect().unwrap();

        conn
    });

    conn
}
