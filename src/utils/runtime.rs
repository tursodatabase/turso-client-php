use std::{collections::HashMap};
use url::{Url, Host};

use ext_php_rs::{
    exception::PhpException,
    types::{ZendHashTable, Zval},
};
use once_cell::sync::OnceCell;
use tokio::runtime::Runtime;

pub fn cwd() -> Result<String, PhpException> {
    match std::env::current_dir() {
        Ok(current_dir) => {
            if let Some(last_component) = current_dir.components().last() {
                let last_dir = last_component.as_os_str().to_string_lossy();
                let slugified_last_dir = slugify(&last_dir);
                Ok(slugified_last_dir)
            } else {
                Err(PhpException::default(
                    "Current working directory is empty".to_string(),
                ))
            }
        }
        Err(err) => Err(PhpException::default(format!(
            "Error getting current working directory: {}",
            err
        ))),
    }
}

pub fn slugify(s: &str) -> String {
    s.chars()
        .map(|c| {
            if c.is_ascii_alphanumeric() {
                c.to_ascii_lowercase()
            } else {
                '_'
            }
        })
        .collect()
}

pub fn remove_duplicates<T: Eq + std::hash::Hash + Clone>(vec: &mut Vec<T>) {
    let mut set = std::collections::HashSet::new();
    let mut i = 0;

    while i < vec.len() {
        if !set.insert(vec[i].clone()) {
            vec.remove(i);
        } else {
            i += 1;
        }
    }
}

/// Converts a LibSQL value to a Zval.
///
/// # Arguments
///
/// * `value` - The LibSQL value to be converted.
///
/// # Returns
///
/// A Result containing the converted Zval or an error.
pub fn convert_libsql_value_to_zval(
    value: libsql::Value,
) -> Result<Zval, ext_php_rs::error::Error> {
    match value {
        libsql::Value::Integer(i) => Ok(ext_php_rs::convert::IntoZval::into_zval(i, false)?),
        libsql::Value::Real(f) => Ok(ext_php_rs::convert::IntoZval::into_zval(f, false)?),
        libsql::Value::Text(s) => Ok(ext_php_rs::convert::IntoZval::into_zval(s, false)?),
        libsql::Value::Blob(b) => Ok(ext_php_rs::convert::IntoZval::into_zval(b, false)?),
        libsql::Value::Null => Ok(Zval::new()),
    }
}

/// Converts a vector of hash maps to a PHP array.
///
/// # Arguments
///
/// * `vec` - A vector of hash maps containing column data.
///
/// # Returns
///
/// A Result containing the converted Zval or an error.
pub fn convert_vec_hashmap_to_php_array(
    vec: Vec<HashMap<String, libsql::Value>>,
) -> Result<Zval, ext_php_rs::error::Error> {
    let mut outer_array = ZendHashTable::new();

    for hashmap in vec {
        let mut inner_array = ZendHashTable::new();

        for (key, column_data) in hashmap {
            let php_value = convert_libsql_value_to_zval(column_data).unwrap();
            inner_array.insert(&key.as_str(), php_value)?;
        }

        let inner_array_zval = ext_php_rs::convert::IntoZval::into_zval(
            ext_php_rs::boxed::ZBox::from(inner_array),
            false,
        )?;
        outer_array.push(inner_array_zval)?;
    }

    ext_php_rs::convert::IntoZval::into_zval(ext_php_rs::boxed::ZBox::from(outer_array), false)
}

/// Retrieves the global runtime instance.
///
/// # Returns
///
/// A reference to the global runtime instance.
pub fn runtime() -> &'static Runtime {
    static RUNTIME: OnceCell<Runtime> = OnceCell::new();

    RUNTIME.get_or_try_init(Runtime::new).unwrap()
}

/// Determines the mode based on the provided URL, authentication token, and sync URL.
///
/// # Arguments
///
/// * `url` - An optional URL.
/// * `auth_token` - An optional authentication token.
/// * `sync_url` - An optional sync URL.
///
/// # Returns
///
/// A string indicating the determined mode:
/// - "remote_replica" if the URL starts with "file:" and the sync URL starts with "libsql://", "http://", or "https://".
/// - "remote" if the URL starts with "libsql://", "http://", or "https://".
/// - "local" if the URL starts with "file:" or contains ":memory:".
/// - "Mode is not available!" if no suitable mode is determined.
pub fn get_mode(
    url: Option<String>,
    auth_token: Option<String>,
    sync_url: Option<String>,
) -> String {
    match (url, auth_token, sync_url) {
        (Some(ref url), Some(ref auth_token), Some(ref sync_url))
            if (url.starts_with("file:") || url.ends_with(".db") || url.starts_with("libsql:"))
                && !auth_token.is_empty()
                && (sync_url.starts_with("libsql://")
                    || sync_url.starts_with("http://")
                    || sync_url.starts_with("https://")) =>
        {
            "remote_replica".to_string()
        }
        (Some(ref url), Some(ref auth_token), _)
            if !auth_token.is_empty() && url.starts_with("libsql://")
                || url.starts_with("http://")
                || url.starts_with("https://") =>
        {
            "remote".to_string()
        }
        (Some(ref url), _, _)
            if url.starts_with("file:")
                || url.ends_with(".db")
                || url.starts_with("libsql:")
                || url.contains(":memory:") =>
        {
            "local".to_string()
        }
        _ => "Mode is not available!".to_string(),
    }
}

#[derive(Debug)]
pub struct Dsn {
    pub dbname: String,
    pub auth_token: String,
}

pub fn parse_dsn(dsn: &str) -> Option<Dsn> {
    // Check if the DSN is empty
    if dsn.is_empty() {
        return Some(Dsn {
            dbname: dsn.to_string(),
            auth_token: "".to_string(),
        });
    }

    // Check if the DSN starts with "libsql:"
    if !dsn.starts_with("libsql:") {
        // Treat it as a filename
        return Some(Dsn {
            dbname: dsn.to_string(),
            auth_token: "".to_string(),
        });
    }

    // Remove the "libsql:" prefix
    let dsn = &dsn[7..];

    let mut parsed_dsn = Dsn {
        dbname: String::new(),
        auth_token: "".to_string(),
    };

    for param in dsn.split(';') {
        let mut parts = param.splitn(2, '=');
        let key = parts.next()?.trim();
        let value = parts.next()?.trim();

        match key {
            "dbname" => parsed_dsn.dbname = value.to_string(),
            "authToken" => parsed_dsn.auth_token = value.to_string(),
            _ => {}
        }
    }

    Some(parsed_dsn)
}

/// Check if a server/URL is reachable
pub fn is_reachable(url: &str) -> bool {
    // Format URL or return false on error
    let transformed_url = match format_url(url) {
        Ok(url) => url,
        Err(_) => return false,
    };

    let client = match reqwest::blocking::Client::builder()
        .timeout(std::time::Duration::from_secs(20))
        .build() 
    {
        Ok(client) => client,
        Err(_) => return false,
    };

    // even errors like 404 mean server is reachable
    match client.get(&transformed_url).send() {
        Ok(_) => true,
        _ => false,
    }
}

pub fn format_url(input_url: &str) -> Result<String, url::ParseError> {
    let mut url = Url::parse(input_url)?;

    if let Some(host) = url.host() {
        if let Host::Domain(domain) = host {
            let domain_lower = domain.to_ascii_lowercase();
            let new_domain = if domain_lower.ends_with(".localhost") || domain_lower == "localhost" {
                "localhost".to_string()
            } else {
                let parts: Vec<&str> = domain.split('.').collect();
                if parts.len() >= 2 {
                    format!("{}.{}", parts[parts.len() - 2], parts[parts.len() - 1])
                } else {
                    domain.to_string()
                }
            };
            let _ = url.set_host(Some(&new_domain));
        }
    }

    url.set_path("/v2");
    url.set_query(None);
    url.set_fragment(None);

    Ok(url.to_string())
}

#[derive(serde::Serialize)]
pub struct WebhookPayload {
    pub event_type: String,
    pub query: Option<String>,
    pub message: Option<String>,
}

pub fn send_webhook_data(url: String, payload: &impl serde::Serialize) -> bool {

    if url.is_empty() {
        return false;
    }

    let client = match reqwest::blocking::Client::builder()
        .timeout(std::time::Duration::from_secs(20))
        .build() {
            Ok(client) => client,
            Err(_) => return false,
        };

    match client.post(url).json(payload).send() {
        Ok(_) => true,
        _ => false,
    }
}
