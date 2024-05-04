use crate::utils::runtime::runtime;

pub fn create_remote_connection(url: String, auth_token: String) -> libsql::Connection {
    let conn = runtime().block_on(async {
        let db = libsql::Builder::new_remote(url, auth_token)
            .build()
            .await
            .unwrap();
        let conn = db.connect().unwrap();
        conn
    });

    conn
}
