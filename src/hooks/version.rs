use crate::LIBSQL_PHP_VERSION;

/// Retrieves the version of LibSQL.
///
/// # Returns
///
/// Returns a string representing the version of LibSQL.
pub fn get_version() -> String {
    let libsql_version =
        "LibSQL Core Version ".to_string() + ": " + &libsql::version() + "-" + &libsql::version_number().to_string()  + " - " + "LibSQL PHP Extension Version: " + LIBSQL_PHP_VERSION;
    libsql_version
}
