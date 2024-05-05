/// Retrieves the version of LibSQL.
///
/// # Returns
///
/// Returns a string representing the version of LibSQL.
pub fn get_version() -> String {
    let lisql_version =
        "LibSQL version ".to_string() + ": " + &libsql::version() + "-" + &libsql::version_number().to_string();
    lisql_version
}
