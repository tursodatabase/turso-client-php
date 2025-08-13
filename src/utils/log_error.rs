use std::fs::OpenOptions;
use std::io::Write;
use std::path::PathBuf;
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
    // File path in /tmp
    let mut file_path = PathBuf::from("/tmp");
    file_path.push("libsql_error.log");

    // Open the file for append, create if it doesn't exist
    if let Ok(mut file) = OpenOptions::new()
        .create(true)
        .append(true)
        .open(&file_path)
    {
        let now = SystemTime::now()
            .duration_since(UNIX_EPOCH)
            .unwrap_or_default()
            .as_secs();
        let _ = writeln!(file, "[{}] {}", now, err);
    }
}
