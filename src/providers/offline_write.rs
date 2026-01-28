use crate::utils::runtime::runtime;

pub fn create_offline_write_connection(
    db_path: String,
    auth_token: String,
    sync_url: String,
) -> (libsql::Database, libsql::Connection) {
    let (db, conn) = runtime().block_on(async {
        let db = libsql::Builder::new_synced_database(db_path, sync_url, auth_token)
            .build()
            .await
            .unwrap();

        let conn = db.connect().unwrap();
        (db, conn)
    });

    (db, conn)
}
