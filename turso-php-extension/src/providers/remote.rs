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
/// Returns a `libsql::Connection` representing the connection to the remote database.
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
