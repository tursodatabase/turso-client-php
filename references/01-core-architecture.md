# Core Architecture Deep Dive

## 1. Registry Pattern: The Central State Management System

### Overview
The entire extension uses **four global, mutex-protected HashMap registries** to manage state. PHP objects are lightweight — they only hold string IDs that reference heavy Rust objects stored in these registries.

### Registry Definitions

```rust
lazy_static::lazy_static! {
    static ref CONNECTION_REGISTRY: Mutex<HashMap<String, libsql::Connection>> = Mutex::new(HashMap::new());
    static ref OFFLINE_CONNECTION_REGISTRY: Mutex<HashMap<String, OfflineWriteConnection>> = Mutex::new(HashMap::new());
    static ref TRANSACTION_REGISTRY: Mutex<HashMap<String, libsql::Transaction>> = Mutex::new(HashMap::new());
    static ref STATEMENT_REGISTRY: Mutex<HashMap<String, libsql::Statement>> = Mutex::new(HashMap::new());
}
```

| Registry | Key Type | Value Type | Purpose |
|----------|----------|------------|---------|
| `CONNECTION_REGISTRY` | UUID v4 String | `libsql::Connection` | Active database connections (local, remote, remote_replica) |
| `OFFLINE_CONNECTION_REGISTRY` | UUID v4 String | `OfflineWriteConnection` | Offline-write mode connections (dual local+remote) |
| `TRANSACTION_REGISTRY` | UUID v4 String | `libsql::Transaction` | Active transactions |
| `STATEMENT_REGISTRY` | UUID v4 String | `libsql::Statement` | Prepared statements |

### Lifecycle of a Registry Entry

**Creation:**
```
PHP: $db = new LibSQL("file:my.db")
  → Rust: conn_id = uuid::Uuid::new_v4().to_string()
  → Create libsql::Connection via provider
  → CONNECTION_REGISTRY.lock().unwrap().insert(conn_id, conn)
  → Return LibSQL { conn_id, mode, ... }
```

**Usage:**
```
PHP: $db->execute("SELECT 1")
  → Rust: conn_registry.get(&self.conn_id)  // Lookup by ID
  → Execute operation on the real connection
```

**Destruction:**
```
PHP: $db->close()
  → Rust: registry.remove(&self.conn_id)  // Remove from registry
  → conn.reset().await  // Clean up resources
```

### Why This Pattern?

1. **PHP object lifecycle mismatch**: PHP objects are garbage collected differently than Rust objects. By storing heavy resources in static registries, Rust controls their lifetime explicitly.

2. **Thread safety**: `Mutex` ensures concurrent PHP requests don't corrupt shared state.

3. **Memory efficiency**: PHP objects are small (just IDs + mode string), while the actual `libsql::Connection` (which holds file handles, network sockets, etc.) lives in Rust.

### Potential Issues

| Issue | Scenario | Mitigation |
|-------|----------|------------|
| **Memory leak** | PHP object dropped without calling `close()` | Registry entry persists until process exit. PHP's `afterEach` in tests cleans up `.db` files. |
| **Deadlock** | Nested `.lock()` calls on different registries | Current code avoids this by locking one registry at a time |
| **Stale IDs** | Using a conn_id after `close()` | Returns "Connection not found" PhpException |

---

## 2. Async Runtime Bridge: Tokio in a Synchronous PHP World

### The Problem
libsql is fully async (uses Tokio), but PHP is synchronous. The bridge solution: **a single global Tokio runtime**.

### Implementation

```rust
// src/utils/runtime.rs
pub fn runtime() -> &'static Runtime {
    static RUNTIME: OnceCell<Runtime> = Runtime::new;
    RUNTIME.get_or_try_init(|| Runtime::new()).unwrap()
}
```

**Key characteristics:**
- `OnceCell` ensures exactly one runtime instance (lazy initialization)
- `&'static` lifetime — lives for the entire process duration
- `Runtime::new()` creates a multi-threaded runtime by default

### Usage Pattern

Every async operation follows this template:

```rust
pub fn exec(conn_id: String, stmt: &str, parameters: Option<QueryParameters>) -> Result<u64, PhpException> {
    // 1. Lookup connection (synchronous)
    let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
    let conn = conn_registry.get(&conn_id).ok_or_else(|| PhpException::from("Connection not found"))?;

    // 2. Execute async operation via block_on
    let result = runtime().block_on(async { conn.execute(stmt, params).await });

    // 3. Handle result
    match result {
        Ok(eresult) => Ok(eresult),
        Err(e) => Err(PhpException::from(format!("Execution error: {}", e))),
    }
}
```

### Implications

| Aspect | Impact |
|--------|--------|
| **No concurrent operations** | Each PHP request blocks on `block_on()`. No parallel queries from a single request. |
| **Runtime overhead** | Single initialization cost, then negligible. `OnceCell` is lock-free after init. |
| **No async PHP needed** | PHP code remains fully synchronous. The async complexity is hidden in Rust. |
| **Thread pool sharing** | All database operations share the same Tokio thread pool. |

### Why Not Create Runtime Per Call?

```rust
// BAD: Creates new runtime every time (expensive, can panic)
let rt = tokio::runtime::Runtime::new().unwrap();
rt.block_on(async { ... });

// GOOD: Reuse global runtime (cheap, safe)
runtime().block_on(async { ... });
```

Creating a runtime is expensive (creates thread pool, I/O driver, timer). Doing it per call would:
- Add 10-100ms overhead per database operation
- Eventually exhaust system resources
- Potentially panic if called from within an existing async context

---

## 3. Type Conversion Pipeline: PHP Zval ↔ Rust Types

### The Challenge
PHP uses `zval` (a tagged union C struct) for all values. Rust uses strongly typed structs. The `ext-php-rs` crate provides `FromZval` and `IntoZval` traits for conversion.

### Input Pipeline: PHP → Rust

```
PHP Array: [":name" => "Alice", ":age" => 30]
    ↓
ext-php-rs: FromZval trait
    ↓
QueryParameters {
    named: Some({":name": QueryValue::Text("Alice"), ":age": QueryValue::Integer(30)}),
    positional: None
}
    ↓
QueryParameters::to_params()
    ↓
libsql::params::Params::Named({":name": Value::Text("Alice"), ":age": Value::Integer(30)})
```

**Implementation** (`src/utils/query_params.rs`):

```rust
impl<'a> FromZval<'a> for QueryParameters {
    const TYPE: DataType = DataType::Mixed;

    fn from_zval(zval: &'a Zval) -> Option<Self> {
        if let Some(array) = zval.array() {
            let mut positional = Vec::new();
            let mut named = HashMap::new();

            for (key, value) in array.iter() {
                match key {
                    ArrayKey::Long(index) => {
                        // Positional: [0] => "Alice", [1] => 30
                        let query_value = if let Some(int_val) = value.long() {
                            QueryValue::Integer(int_val)
                        } else if let Some(float_val) = value.double() {
                            QueryValue::Real(float_val)
                        } else if let Some(text_val) = value.string() {
                            QueryValue::Text(text_val.to_string())
                        } else if value.is_null() {
                            QueryValue::Null
                        } else { continue; };

                        positional.resize((index + 1) as usize, QueryValue::Null);
                        positional[index as usize] = query_value;
                    }
                    ArrayKey::String(key) => {
                        // Named: ":name" => "Alice"
                        let query_value = /* same type detection */;
                        named.insert(key.to_string(), query_value);
                    }
                }
            }

            Some(QueryParameters {
                positional: if positional.is_empty() { None } else { Some(positional) },
                named: if named.is_empty() { None } else { Some(named) },
            })
        } else {
            None
        }
    }
}
```

### Output Pipeline: Rust → PHP

```
libsql::Value::Text("Alice")
    ↓
convert_libsql_value_to_zval()
    ↓
Zval { value: { string: "Alice" }, type_info: IS_STRING }
    ↓
ZendHashTable / PHP Array
    ↓
PHP: [['name' => 'Alice', 'age' => 30]]
```

**Implementation** (`src/utils/runtime.rs`):

```rust
pub fn convert_libsql_value_to_zval(value: libsql::Value) -> Result<Zval, ext_php_rs::error::Error> {
    match value {
        libsql::Value::Integer(i) => Ok(ext_php_rs::convert::IntoZval::into_zval(i, false)?),
        libsql::Value::Real(f) => Ok(ext_php_rs::convert::IntoZval::into_zval(f, false)?),
        libsql::Value::Text(s) => Ok(ext_php_rs::convert::IntoZval::into_zval(s, false)?),
        libsql::Value::Blob(b) => Ok(ext_php_rs::convert::IntoZval::into_zval(b, false)?),
        libsql::Value::Null => Ok(Zval::new()),  // Empty Zval = null
    }
}
```

### Type Mapping

| PHP Type | Rust QueryValue | libsql::Value | Zval Type |
|----------|----------------|---------------|-----------|
| `int` | `QueryValue::Integer(i64)` | `Value::Integer(i64)` | `IS_LONG` |
| `float` | `QueryValue::Real(f64)` | `Value::Real(f64)` | `IS_DOUBLE` |
| `string` | `QueryValue::Text(String)` | `Value::Text(String)` | `IS_STRING` |
| `null` | `QueryValue::Null` | `Value::Null` | `IS_NULL` |
| `string` (binary) | `QueryValue::Blob(Vec<u8>)` | `Value::Blob(Vec<u8>)` | `IS_STRING` |

### ConfigValue Parsing

The `ConfigValue` enum handles the constructor's first argument:

```rust
#[derive(Debug, Clone)]
pub enum ConfigValue {
    String(String),                                    // DSN string
    Array(std::collections::HashMap<String, ConfigValue>),  // Config array
}
```

**PHP → Rust mapping:**
```php
// String form
new LibSQL("file:database.db")
    → ConfigValue::String("file:database.db")

// Array form
new LibSQL(["url" => "file:db.db", "authToken" => "..."])
    → ConfigValue::Array({"url": String("file:db.db"), "authToken": String("...")})
```

---

## 4. Mode Detection Algorithm

The `get_mode()` function in `src/utils/runtime.rs` determines which provider to use:

```rust
pub fn get_mode(url: Option<String>, auth_token: Option<String>, sync_url: Option<String>) -> String {
    match (url, auth_token, sync_url) {
        // Case 1: remote_replica
        // file: or .db + non-empty auth_token + http/libsql sync_url
        (Some(ref url), Some(ref auth_token), Some(ref sync_url))
            if (url.starts_with("file:") || url.ends_with(".db") || url.starts_with("libsql:"))
                && !auth_token.is_empty()
                && (sync_url.starts_with("libsql://") || sync_url.starts_with("http://") || sync_url.starts_with("https://")) =>
        {
            "remote_replica".to_string()
        }

        // Case 2: remote
        // libsql:// or http(s):// + non-empty auth_token
        (Some(ref url), Some(ref auth_token), _)
            if !auth_token.is_empty() && (url.starts_with("libsql://") || url.starts_with("http://") || url.starts_with("https://")) =>
        {
            "remote".to_string()
        }

        // Case 3: local
        // file: or .db or libsql: or :memory:
        (Some(ref url), _, _)
            if url.starts_with("file:") || url.ends_with(".db") || url.starts_with("libsql:") || url.contains(":memory:") =>
        {
            "local".to_string()
        }

        // Case 4: fallback
        _ => "Mode is not available!".to_string(),
    }
}
```

### Decision Tree

```
Config received
    │
    ├── Has url + authToken + syncUrl?
    │   ├── url starts with "file:" or ends with ".db"?
    │   │   └── YES → "remote_replica" (local file + remote sync)
    │   │
    │   └── url starts with "libsql://"?
    │       └── YES → "remote" (pure remote connection)
    │
    ├── Has url (no authToken or syncUrl)?
    │   ├── url starts with "file:" or contains ":memory:"?
    │   │   └── YES → "local" (local SQLite)
    │   │
    │   └── url starts with "libsql://"?
    │       └── YES → "remote" (remote via libsql protocol)
    │
    └── Nothing matches?
        └── "Mode is not available!" (error)
```

### DSN Parsing

The `parse_dsn()` function handles the `libsql:dbname=...;authToken=...` format:

```rust
pub fn parse_dsn(dsn: &str) -> Option<Dsn> {
    if dsn.is_empty() {
        return Some(Dsn { dbname: dsn.to_string(), auth_token: "".to_string() });
    }

    if !dsn.starts_with("libsql:") {
        // Treat as filename
        return Some(Dsn { dbname: dsn.to_string(), auth_token: "".to_string() });
    }

    // Remove "libsql:" prefix and parse key=value pairs
    let dsn = &dsn[7..];
    let mut parsed_dsn = Dsn { dbname: String::new(), auth_token: "".to_string() };

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
```

**Examples:**
| DSN | dbname | auth_token |
|-----|--------|------------|
| `database.db` | `database.db` | `""` |
| `file:my.db` | `file:my.db` | `""` |
| `libsql:dbname=https://db.turso.io;authToken=tok_abc` | `https://db.turso.io` | `tok_abc` |
| `:memory:` | `:memory:` | `""` |

---

## 5. Error Handling Strategy

### Pattern: Result<T, PhpException>

Every PHP-facing function returns `Result<T, PhpException>`:

```rust
pub fn exec(conn_id: String, stmt: &str, parameters: Option<QueryParameters>) -> Result<u64, PhpException> {
    // ...
}
```

### Error Creation Methods

| Method | Use Case | Example |
|--------|----------|---------|
| `PhpException::default(msg)` | New exception with default type | `PhpException::default("Mutex lock error".into())` |
| `PhpException::from(msg)` | From string | `PhpException::from("Connection not found")` |

### Error Logging

Errors are logged to `/tmp/libsql_error.log` via `log_error_to_tmp()`:

```rust
pub fn log_error_to_tmp(err: &str) {
    let mut file_path = PathBuf::from("/tmp");
    file_path.push("libsql_error.log");

    if let Ok(mut file) = OpenOptions::new()
        .create(true)
        .append(true)
        .open(&file_path)
    {
        let now = SystemTime::now().duration_since(UNIX_EPOCH).unwrap_or_default().as_secs();
        let _ = writeln!(file, "[{}] {}", now, err);
    }
}
```

**Format:** `[1712345678] Connection not found`

### Error Flow

```
Rust error (libsql::Error)
    ↓
format!("Execution error: {}", e)
    ↓
log_error_to_tmp(&err_msg)  // Log to /tmp/libsql_error.log
    ↓
PhpException::from(err_msg)  // Create PHP exception
    ↓
Result::Err(exception)  // Return to PHP
    ↓
PHP throws exception
```

### Inconsistency Note

Some code paths use `.unwrap()` instead of proper error handling (especially in `load_extensions.rs` and providers). This can cause PHP fatal crashes instead of graceful exceptions.

```rust
// In load_extensions.rs - DANGEROUS
conn.load_extension_enable().unwrap();  // Will panic on failure

// Better approach:
conn.load_extension_enable().map_err(|e| PhpException::from(format!("{:?}", e)))?;
```
