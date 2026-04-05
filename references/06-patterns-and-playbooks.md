# Patterns, Anti-Patterns, and Practical Playbooks

## Design Patterns Used in the Codebase

### 1. Registry Pattern (Global State Management)

**Where used:** All stateful objects (connections, transactions, statements)

**Pattern:**
```rust
// Definition (lib.rs)
lazy_static::lazy_static! {
    static ref SOME_REGISTRY: Mutex<HashMap<String, SomeType>> = Mutex::new(HashMap::new());
}

// Creation
let id = uuid::Uuid::new_v4().to_string();
SOME_REGISTRY.lock().unwrap().insert(id.clone(), some_object);
php_object.id = id;  // PHP object only holds the ID

// Lookup
let registry = SOME_REGISTRY.lock().unwrap();
let obj = registry.get(&self.id).ok_or_else(|| PhpException::from("Not found"))?;

// Removal
let mut registry = SOME_REGISTRY.lock().unwrap();
registry.remove(&self.id);
```

**Why this pattern:**
- PHP objects are garbage collected unpredictably
- Rust needs explicit control over resource lifetimes
- Mutex ensures thread safety across PHP requests
- PHP objects stay lightweight (just IDs + metadata)

**When to reuse:** Any time you need to manage heavy Rust resources referenced from PHP.

---

### 2. Provider Pattern (Strategy Pattern)

**Where used:** Connection mode implementations in `src/providers/`

**Pattern:**
```rust
// Each provider is an isolated module
pub mod local;
pub mod remote;
pub mod remote_replica;
pub mod offline_write;
pub mod sqld_offline_write;

// Each exposes a create_*_connection() function
pub fn create_local_connection(...) -> Result<libsql::Connection, PhpException> { ... }
pub fn create_remote_connection(...) -> libsql::Connection { ... }
pub fn create_remote_replica_connection(...) -> (libsql::Database, libsql::Connection) { ... }
```

**Selection logic:**
```rust
// In lib.rs constructor
let (conn, db) = match mode.as_str() {
    "local" => {
        let conn = providers::local::create_local_connection(...)?;
        (conn, None)
    }
    "remote" => {
        let conn = providers::remote::create_remote_connection(...);
        (conn, None)
    }
    "remote_replica" => {
        let (db, conn) = if offline_writes {
            providers::offline_write::create_offline_write_connection(...)
        } else {
            providers::remote_replica::create_remote_replica_connection(...)
        };
        (conn, Some(db))
    }
    _ => return Err(PhpException::default("Mode is not available!".into())),
};
```

**Why this pattern:**
- Each mode has completely different initialization logic
- Easy to add new modes without touching existing ones
- Clear separation of concerns
- Testable in isolation

**When to reuse:** Adding a new connection mode, or any feature with multiple implementation strategies.

---

### 3. Hook Pattern (Delegation)

**Where used:** Database operations in `src/hooks/`

**Pattern:**
```rust
// Hook module (e.g., hooks/use_exec.rs)
pub fn exec(conn_id: String, stmt: &str, parameters: Option<QueryParameters>) -> Result<u64, PhpException> {
    // 1. Look up connection from registry
    let conn_registry = CONNECTION_REGISTRY.lock().map_err(...)?;
    let conn = conn_registry.get(&conn_id).ok_or_else(...)?;

    // 2. Execute operation
    let result = runtime().block_on(async { conn.execute(stmt, params).await });

    // 3. Return result
    match result {
        Ok(eresult) => Ok(eresult),
        Err(e) => Err(PhpException::from(format!("Execution error: {}", e))),
    }
}
```

**Called from main class:**
```rust
// In lib.rs LibSQL::execute()
pub fn execute(&self, stmt: &str, parameters: Option<QueryParameters>) -> Result<u64, PhpException> {
    if self.mode == "offline_write" {
        // Special handling for offline mode
        ...
    } else {
        // Delegate to hook
        hooks::use_exec::exec(self.conn_id.to_string(), stmt, parameters)
    }
}
```

**Why this pattern:**
- Keeps `lib.rs` from becoming a monolith
- Each hook is a single responsibility function
- Easy to test hooks independently
- Consistent error handling pattern

**When to reuse:** Any database operation that needs registry lookup + execution + error handling.

---

### 4. Type Conversion Pipeline (Adapter Pattern)

**Where used:** PHP ↔ Rust type conversions

**Input adapter (PHP → Rust):**
```rust
impl<'a> FromZval<'a> for QueryParameters {
    const TYPE: DataType = DataType::Mixed;

    fn from_zval(zval: &'a Zval) -> Option<Self> {
        if let Some(array) = zval.array() {
            // Parse PHP array → Rust struct
            Some(QueryParameters { ... })
        } else {
            None
        }
    }
}
```

**Output adapter (Rust → PHP):**
```rust
impl IntoZval for ResultSet {
    const TYPE: DataType = DataType::Array;
    const NULLABLE: bool = false;

    fn set_zval(self, zv: &mut Zval, _: bool) -> Result<(), Error> {
        // Convert Rust struct → PHP array
        let mut array = ZendHashTable::new();
        array.insert("columns", columns_array)?;
        array.insert("rows", rows_array)?;
        *zv = array.into_zval(false)?;
        Ok(())
    }
}
```

**Value converter (utility function):**
```rust
pub fn convert_libsql_value_to_zval(value: libsql::Value) -> Result<Zval, Error> {
    match value {
        libsql::Value::Integer(i) => IntoZval::into_zval(i, false),
        libsql::Value::Real(f) => IntoZval::into_zval(f, false),
        libsql::Value::Text(s) => IntoZval::into_zval(s, false),
        libsql::Value::Blob(b) => IntoZval::into_zval(b, false),
        libsql::Value::Null => Ok(Zval::new()),
    }
}
```

**Why this pattern:**
- `ext-php-rs` requires `FromZval`/`IntoZval` for custom types
- Centralizes conversion logic
- Reusable across all modules

---

### 5. Async Bridge Pattern (Blocking Adapter)

**Where used:** Every async operation

**Pattern:**
```rust
pub fn some_operation(&self) -> Result<T, PhpException> {
    let result = runtime().block_on(async {
        // All async code goes here
        some_async_function().await
    });

    match result {
        Ok(value) => Ok(value),
        Err(e) => Err(PhpException::from(format!("{:?}", e))),
    }
}
```

**Why this pattern:**
- PHP is synchronous, libSQL is async
- Single global Tokio runtime (`OnceCell<Runtime>`)
- `block_on()` bridges the sync/async boundary
- PHP code remains fully synchronous

---

### 6. Lazy Iterator Pattern

**Where used:** `LibSQLIterator` for `LIBSQL_LAZY` fetch mode

**Pattern:**
```rust
#[php_class]
pub struct LibSQLIterator {
    data: Rc<Zval>,    // Shared ownership of PHP array
    counter: i32,       // Current position
}

#[php_impl]
impl LibSQLIterator {
    pub fn current(&self) -> Option<Zval> { ... }
    pub fn key(&self) -> i32 { ... }
    pub fn next(&mut self) { self.counter += 1; }
    pub fn rewind(&mut self) { self.counter = 0; }
    pub fn valid(&self) -> bool { ... }
}
```

**PHP usage:**
```php
$iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);
foreach ($iterator as $row) {
    // Processes one row at a time
}
```

**Why this pattern:**
- Memory-efficient iteration over large result sets
- Implements PHP's Traversable interface
- `Rc<Zval>` allows shared ownership without cloning

---

## Anti-Patterns Found in the Codebase

### 1. Using `.unwrap()` in PHP-Facing Code

**Where:** `providers/remote.rs`, `providers/remote_replica.rs`, `providers/offline_write.rs`, `hooks/load_extensions.rs`

**Problem:**
```rust
// In remote.rs
let db = libsql::Builder::new_remote(url, auth_token)
    .build()
    .await
    .unwrap();  // ← Will PANIC on failure

let conn = db.connect().unwrap();  // ← Will PANIC on failure
```

**Why it's bad:**
- Rust panic = PHP fatal error (no graceful exception handling)
- User sees a crash instead of a meaningful error message
- Violates the project's own error handling convention

**Better approach:**
```rust
let db = libsql::Builder::new_remote(url, auth_token)
    .build()
    .await
    .map_err(|e| PhpException::default(format!("Database build failed: {}", e)))?;

let conn = db.connect()
    .map_err(|e| PhpException::default(format!("Connection failed: {}", e)))?;
```

---

### 2. Inconsistent Error Handling Between Providers

**Where:** Compare `local.rs` vs `remote.rs`

**Problem:**
```rust
// local.rs — GOOD
let db = libsql::Builder::new_local(url)
    .build()
    .await
    .map_err(|e| PhpException::default(format!("Database build failed: {}", e)))?;

// remote.rs — BAD
let db = libsql::Builder::new_remote(url, auth_token)
    .build()
    .await
    .unwrap();  // Inconsistent!
```

**Why it's bad:**
- Developers can't predict error behavior by convention
- Some connection failures crash PHP, others throw exceptions
- Makes debugging unpredictable

---

### 3. Hardcoded Auth Tokens in Test Files

**Where:** `tests/Unit/RemoteConnectionTest.php`, `examples/remote.php`, `examples/remote-replica.php`

**Problem:**
```php
$authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDA2Nzc2ODU...';
```

**Why it's bad:**
- Security risk if token is valid
- Tests depend on specific token format
- Examples won't work for other users without modification

**Better approach:**
```php
$authToken = getenv("TURSO_AUTH_TOKEN") ?: "test_token_placeholder";
```

---

### 4. Mutex Locking Inconsistency

**Where:** Various hook files

**Problem:**
```rust
// In use_exec.rs — GOOD (with error handling)
let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
    let err_msg = format!("Mutex lock error: {}", e);
    log_error_to_tmp(&err_msg);
    PhpException::default(err_msg)
})?;

// In use_exec_batch.rs — OK (unwrap, but safe in practice)
let conn_registry = CONNECTION_REGISTRY.lock().unwrap();

// In transaction.rs — OK
let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
```

**Why it matters:**
- `.unwrap()` on mutex lock will panic if another thread poisoned the mutex
- In practice, this only happens if a previous holder panicked while holding the lock
- The `.map_err()` pattern is safer but more verbose

---

### 5. ConfigValue Missing Type Implementations

**Where:** `src/utils/config_value.rs`

**Problem:**
```rust
impl ConfigValue {
    pub fn to_long(&self) -> Option<u64> {
        None  // Always returns None!
    }

    pub fn to_bool(&self) -> Option<bool> {
        None  // Always returns None!
    }
}
```

**Why it's bad:**
- Methods exist but don't work — dead code
- Config array parsing can't extract numeric or boolean values properly
- The constructor works around this by parsing manually

**What actually happens:**
```rust
// In lib.rs constructor — manual parsing instead of using ConfigValue methods
let sync_interval = config
    .get("syncInterval")
    .and_then(|s| s.to_long())  // Returns None!
    .map(std::time::Duration::from_secs)
    .unwrap_or_else(|| std::time::Duration::from_secs(5));  // Falls back to default
```

The code works because it falls back to defaults, but the `to_long()` and `to_bool()` methods are misleading.

---

### 6. Error Log Path Hardcoded to `/tmp`

**Where:** `src/utils/log_error.rs`

**Problem:**
```rust
pub fn log_error_to_tmp(err: &str) {
    let mut file_path = PathBuf::from("/tmp");
    file_path.push("libsql_error.log");
    // ...
}
```

**Why it's bad:**
- `/tmp` doesn't exist on Windows
- No fallback for different operating systems
- Errors silently disappear on Windows

**Better approach:**
```rust
pub fn log_error_to_tmp(err: &str) {
    let temp_dir = std::env::temp_dir();  // Cross-platform
    let mut file_path = temp_dir.join("libsql_error.log");
    // ...
}
```

---

### 7. Typo in Test Filename

**Where:** `tests/Feature/PerpareStatmentTest.php`

Should be `PrepareStatementTest.php`. Minor but affects discoverability.

---

## Practical Playbooks

### Playbook 1: Add a New Method to the LibSQL Class

**Scenario:** Add a `ping()` method that checks if the database is responsive.

**Steps:**

1. **Add the method to `src/lib.rs`:**

```rust
#[php_impl]
impl LibSQL {
    // ... existing methods ...

    /// Pings the database to check connectivity.
    pub fn ping(&self) -> Result<bool, PhpException> {
        if self.mode == "offline_write" {
            let offline_registry = OFFLINE_CONNECTION_REGISTRY.lock().unwrap();
            let offline_conn = offline_registry
                .get(&self.conn_id)
                .ok_or_else(|| PhpException::from("Offline connection not found"))?;

            // Try a simple query on local connection
            match runtime().block_on(async {
                offline_conn.local_conn.execute("SELECT 1", libsql::params![]).await
            }) {
                Ok(_) => Ok(true),
                Err(_) => Ok(false),
            }
        } else {
            let conn_registry = CONNECTION_REGISTRY.lock().map_err(|e| {
                PhpException::default(format!("Mutex lock error: {}", e))
            })?;

            let conn = conn_registry.get(&self.conn_id).ok_or_else(|| {
                PhpException::from("Connection not found")
            })?;

            match runtime().block_on(async {
                conn.execute("SELECT 1", libsql::params![]).await
            }) {
                Ok(_) => Ok(true),
                Err(_) => Ok(false),
            }
        }
    }
}
```

2. **Update PHP stubs (`libsql_php_extension.stubs.php`):**

```php
class LibSQL {
    // ... existing methods ...

    /**
     * Pings the database to check connectivity.
     *
     * @return bool True if the database is responsive, false otherwise.
     */
    public function ping() {}
}
```

3. **Add a test (`tests/Feature/PingTest.php`):**

```php
<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Ping', function () {
    test('ping returns true for healthy connection', function () {
        expect($this->db->ping())->toBeTrue();
    });
})->group('PingTest', 'Feature');
```

4. **Build and verify:**

```bash
cargo build
composer test
```

---

### Playbook 2: Add a New Connection Mode

**Scenario:** Add a "read_replica" mode that connects to a read-only replica.

**Steps:**

1. **Create provider: `src/providers/read_replica.rs`**

```rust
use ext_php_rs::prelude::PhpException;
use crate::utils::runtime::runtime;

pub fn create_read_replica_connection(
    url: String,
    auth_token: String,
) -> libsql::Connection {
    runtime().block_on(async {
        let db = libsql::Builder::new_remote(url, auth_token)
            .build()
            .await
            .map_err(|e| PhpException::default(format!("Replica build failed: {}", e)))?;

        db.connect()
            .map_err(|e| PhpException::default(format!("Replica connection failed: {}", e)))
    })
}
```

2. **Register module: `src/providers/mod.rs`**

```rust
pub mod local;
pub mod remote;
pub mod remote_replica;
pub mod offline_write;
pub mod sqld_offline_write;
pub mod read_replica;  // ← Add this
```

3. **Update mode detection: `src/utils/runtime.rs`**

```rust
pub fn get_mode(url: Option<String>, auth_token: Option<String>, sync_url: Option<String>) -> String {
    match (url, auth_token, sync_url) {
        // ... existing cases ...

        // New case: read_replica (remote URL with "replica" in config)
        (Some(ref url), Some(ref auth_token), _)
            if url.contains("replica") && !auth_token.is_empty() =>
        {
            "read_replica".to_string()
        }

        _ => "Mode is not available!".to_string(),
    }
}
```

4. **Add constructor handling: `src/lib.rs`**

```rust
let (conn, db) = match mode.as_str() {
    // ... existing cases ...

    "read_replica" => {
        let conn = providers::read_replica::create_read_replica_connection(
            url,
            auth_token,
        )?;
        (conn, None)
    }

    _ => return Err(PhpException::default("Mode is not available!".into())),
};
```

5. **Update stubs, add tests, build.**

---

### Playbook 3: Debug a "Connection Not Found" Error

**Symptom:** PHP throws "Connection not found" exception.

**Debugging steps:**

1. **Check if the connection was ever created:**
   - Look for the `LibSQL::__construct()` call
   - Verify it didn't throw an exception during construction

2. **Check if `close()` was called prematurely:**
   ```php
   $db->close();
   $db->query("SELECT 1");  // ← "Connection not found"
   ```

3. **Check the conn_id in Rust:**
   Add temporary debug output in the relevant hook:
   ```rust
   pub fn exec(conn_id: String, ...) -> Result<u64, PhpException> {
       eprintln!("DEBUG: Looking for conn_id: {}", conn_id);

       let conn_registry = CONNECTION_REGISTRY.lock().unwrap();
       eprintln!("DEBUG: Registry contains: {:?}", conn_registry.keys());

       // ... rest of function
   }
   ```

4. **Check for registry corruption:**
   - If a previous operation panicked while holding the mutex, the mutex becomes "poisoned"
   - `.lock()` will return `Err(PoisonError)` on a poisoned mutex
   - Look for panic messages before the "Connection not found" error

5. **Check for UUID collision (extremely unlikely):**
   - Each connection gets a UUID v4
   - Collision probability is ~1 in 2^122

---

### Playbook 4: Debug a Sync Failure in Offline Mode

**Symptom:** `$db->sync()` throws an exception.

**Debugging steps:**

1. **Check connectivity:**
   ```php
   echo $db->isOnline() ? "Online (cached)" : "Offline (cached)";
   echo $db->checkConnectivity() ? "Online (fresh)" : "Offline (fresh)";
   ```

2. **Check pending operations:**
   ```php
   echo "Pending: " . $db->getPendingOperationsCount();
   ```

3. **Check error log:**
   ```bash
   cat /tmp/libsql_error.log  # Linux/macOS
   # On Windows, check %TEMP%\libsql_error.log (if it exists)
   ```

4. **Test remote connectivity manually:**
   ```bash
   curl -v https://your-sqld-server.com/v2
   ```

5. **Check if initial sync completed:**
   Connect to the local database and check:
   ```php
   $result = $db->query("SELECT value FROM libsql_metadata WHERE key = 'initial_sync_done'");
   $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);
   var_dump($row);  // Should be ['value' => 'true']
   ```

6. **Check pending operations table:**
   ```php
   $result = $db->query("SELECT * FROM libsql_pending_ops");
   $ops = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
   foreach ($ops as $op) {
       echo "SQL: {$op['sql']}, Type: {$op['operation_type']}, Time: {$op['timestamp']}\n";
   }
   ```

---

### Playbook 5: Build and Install Extension Manually

**Steps:**

1. **Ensure Rust nightly:**
   ```bash
   rustup toolchain install nightly
   rustup default nightly
   ```

2. **Build:**
   ```bash
   cargo build --release
   ```

3. **Find the binary:**
   ```bash
   # Linux
   ls target/release/liblibsql_php.so

   # macOS
   ls target/release/liblibsql_php.dylib

   # Windows
   ls target/release/libsql_php.dll
   ```

4. **Install:**
   ```bash
   # Find PHP extension directory
   php -i | grep extension_dir

   # Copy extension
   cp target/release/liblibsql_php.so /path/to/php/extensions/

   # Enable in php.ini
   echo "extension=liblibsql_php.so" >> /path/to/php.ini
   ```

5. **Verify:**
   ```bash
   php -m | grep libsql
   php --ri libsql
   ```

---

### Playbook 6: Add a Test for a New Feature

**Pattern to follow:**

```php
<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Feature Name', function () {
    // Setup shared before all tests in this describe block
    beforeEach(function () {
        $this->db->execute("CREATE TABLE test_table (
            id INTEGER PRIMARY KEY,
            name TEXT
        )");
    });

    test('basic functionality works', function () {
        $result = $this->db->execute("INSERT INTO test_table (name) VALUES (?)", ['test']);
        expect($result)->toBe(1);
    })->group('FeatureNameTest', 'Feature');

    test('edge case is handled', function () {
        expect(fn() => $this->db->execute("INVALID SQL"))->toThrow(Exception::class);
    })->group('FeatureNameTest', 'Feature');

    test('another scenario', function () {
        $this->db->execute("INSERT INTO test_table (name) VALUES (?)", ['Alice']);
        $result = $this->db->query("SELECT * FROM test_table");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Alice');
    })->group('FeatureNameTest', 'Feature');
});
```

**Key conventions:**
- Use `uses(TestCase::class)` for `:memory:` database
- Use `describe()` for grouping related tests
- Use `beforeEach()` for shared setup
- Tag with `->group('TestName', 'Feature')` for filtering
- Use Pest's expressive assertions: `expect()->toBe()`, `->toThrow()`, `->toHaveCount()`
