use std::fs::OpenOptions;
use std::io::Write;
use std::time::{SystemTime, UNIX_EPOCH};

/// Logs an error message to a temporary file located at `/tmp/libsql_error.log`.
///
/// The function appends the given error message to the log file, prefixed with
/// the current system time in seconds since the UNIX epoch. If the file does not
/// exist, it will be created.
///
/// # Arguments
///
/// * `err` - A string slice that holds the error message to be logged.

pub fn log_error_to_tmp(err: &str) {
    // Opt-in diagnostics: by default this does nothing so that PHP request
    // processing never writes hidden files containing SQL and parameter
    // contents to shared tmp. Set LIBSQL_PHP_ERROR_LOG to a file path to
    // enable it.
    let Ok(file_path) = std::env::var("LIBSQL_PHP_ERROR_LOG") else {
        return;
    };
    if file_path.is_empty() {
        return;
    }

    // Open the file for append, create if it doesn't exist
    if let Ok(mut file) = OpenOptions::new().create(true).append(true).open(file_path) {
        let now = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap_or_default()
            .as_secs();
        let _ = writeln!(file, "[{}] {}", now, err);
    }
}
