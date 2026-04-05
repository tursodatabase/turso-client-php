# Offline Writes Deep Dive

## Overview

Offline writes are the **most complex feature** in this extension. They enable applications to function fully when disconnected from the network, queueing all write operations locally and syncing them when connectivity is restored.

There are **two implementations**:
1. **Turso Cloud** (`offline_write.rs`) — Uses libSQL's built-in synced database
2. **Self-hosted sqld** (`sqld_offline_write.rs`) — Custom implementation with full control

This document focuses on the **self-hosted sqld** implementation since it contains the most logic.

---

## Architecture

### Data Structures

```rust
pub struct OfflineWriteConnection {
    pub local_conn: libsql::Connection,           // Local database (always writable)
    pub remote_conn: libsql::Connection,          // Remote connection (for sync)
    pub remote_url: String,                       // Remote server URL
    pub is_online: Arc<Mutex<bool>>,              // Cached connectivity status
    pub pending_operations: Arc<Mutex<Vec<PendingOperation>>>,  // Operation queue
}

#[derive(Debug, Clone)]
pub struct PendingOperation {
    pub id: Option<i64>,                          // Row ID in libsql_pending_ops
    pub sql: String,                              // SQL statement
    pub params: libsql::params::Params,           // Bound parameters
    pub operation_type: OperationType,            // Execute or ExecuteBatch
    pub timestamp: std::time::SystemTime,         // When operation was queued
}

#[derive(Debug, Clone)]
pub enum OperationType {
    Execute,       // Single statement with params
    ExecuteBatch,  // Multiple statements (no params)
}
```

### Local Database Schema

Two internal tables are created during initialization:

```sql
-- Stores queued operations
CREATE TABLE IF NOT EXISTS libsql_pending_ops (
    id INTEGER PRIMARY KEY,           -- Auto-increment ID
    sql TEXT NOT NULL,                -- The SQL statement
    params_json TEXT NOT NULL,        -- Parameters as JSON
    operation_type TEXT NOT NULL,     -- "Execute" or "ExecuteBatch"
    timestamp INTEGER NOT NULL        -- Unix timestamp
);

-- Stores metadata
CREATE TABLE IF NOT EXISTS libsql_metadata (
    key TEXT PRIMARY KEY,             -- Metadata key
    value TEXT                        -- Metadata value
);
```

**Metadata keys used:**
| Key | Value | When Set |
|-----|-------|----------|
| `schema_initialized` | `"true"` | During first initialization |
| `initial_sync_done` | `"true"` | After first successful schema + data sync |

---

## Initialization Flow

### Step-by-Step

```
1. Create local connection
   └─► providers::local::create_local_connection(db_path, flags, encryption_key)

2. Check if already initialized
   └─► SELECT value FROM libsql_metadata WHERE key = 'schema_initialized'
   └─► If result == "true" → skip to step 5

3. Initialize local schema
   └─► PRAGMA synchronous = NORMAL
   └─► PRAGMA journal_mode = WAL
   └─► CREATE TABLE libsql_pending_ops (...)
   └─► CREATE TABLE libsql_metadata (...)
   └─► INSERT INTO libsql_metadata ('schema_initialized', 'true')

4. Create remote connection
   └─► providers::remote::create_remote_connection(sync_url, auth_token)

5. Check connectivity
   └─► is_reachable(sync_url) → is_online

6. If online → initial sync
   └─► initial_sync_if_needed()

7. Load pending operations
   └─► SELECT * FROM libsql_pending_ops ORDER BY id
   └─► Populate pending_operations Vec
```

### Initialization Code Flow

```rust
pub fn new(
    db_path: String,
    auth_token: String,
    sync_url: String,
    flags: Option<i32>,
    encryption_key: Option<String>,
) -> Result<Self, PhpException> {
    // Step 1: Create local connection
    let local_conn = providers::local::create_local_connection(
        db_path.clone(),
        Some(flags.unwrap_or(6)),
        Some(encryption_key.unwrap_or_default()),
    )?;

    // Step 2: Check initialization status
    let already_initialized: bool = runtime().block_on(async {
        let stmt = local_conn
            .prepare("SELECT value FROM libsql_metadata WHERE key = 'schema_initialized'")
            .await?;
        let mut rows = stmt.query(libsql::params::Params::None).await?;
        if let Some(row) = rows.next().await? {
            let value: String = row.get(0)?;
            Ok(value == "true")
        } else {
            Ok(false)
        }
    }).map_err(|err| PhpException::default(format!("DB check failed: {}", err)))?;

    // Step 3: Initialize if needed
    if !already_initialized {
        runtime().block_on(async {
            local_conn.execute_batch("
                PRAGMA synchronous = NORMAL;
                PRAGMA journal_mode = WAL;
                CREATE TABLE IF NOT EXISTS libsql_pending_ops (...);
                CREATE TABLE IF NOT EXISTS libsql_metadata (...);
            ").await
        });

        runtime().block_on(async {
            local_conn.execute(
                "INSERT OR REPLACE INTO libsql_metadata (key, value) VALUES ('schema_initialized', 'true')",
                libsql::params![],
            ).await
        });
    }

    // Step 4: Create remote connection
    let remote_conn = providers::remote::create_remote_connection(sync_url.clone(), auth_token);

    // Step 5: Check connectivity
    let initial_online_status = crate::utils::runtime::is_reachable(&sync_url);

    // Step 6: Create struct
    let connection = Self {
        local_conn,
        remote_conn,
        remote_url: sync_url.clone(),
        is_online: Arc::new(Mutex::new(initial_online_status)),
        pending_operations: Arc::new(Mutex::new(Vec::new())),
    };

    // Step 7: Initial sync if online
    if initial_online_status {
        let _ = connection.initial_sync_if_needed();
    }

    // Step 8: Load pending operations
    connection.load_pending_operations();

    Ok(connection)
}
```

---

## Initial Sync Flow

The initial sync bootstraps the local database with the remote schema and data.

### Algorithm

```
initial_sync_if_needed():
│
├─► Check initial_sync_done in metadata
│   └─► If "true" → return Ok(())
│
├─► Fetch remote schema
│   └─► SELECT sql FROM sqlite_master
│       WHERE type IN ('table', 'index', 'view')
│       AND name NOT LIKE 'libsql_%'
│   └─► For each schema:
│       └─► Add "IF NOT EXISTS" if CREATE statement
│       └─► Execute locally
│
├─► Fetch remote table names
│   └─► SELECT name FROM sqlite_master
│       WHERE type = 'table'
│       AND name NOT LIKE 'libsql_%'
│
├─► For each table:
│   ├─► Fetch all rows: SELECT * FROM table
│   ├─► Get column names: PRAGMA table_info(table)
│   └─► Insert locally: INSERT OR IGNORE INTO table (...) VALUES (...)
│
└─► Mark initial_sync_done = true
```

### Schema Modification

The `add_if_not_exists()` method modifies CREATE statements to be idempotent:

```rust
fn add_if_not_exists(&self, sql: &str) -> String {
    let lower_sql = sql.to_lowercase();

    if lower_sql.starts_with("create table") && !lower_sql.contains("if not exists") {
        // "CREATE TABLE users (id INT)" → "CREATE TABLE IF NOT EXISTS users (id INT)"
        if let Some(idx) = lower_sql.find("table") {
            let (before, after) = sql.split_at(idx + "table".len());
            return format!("{} IF NOT EXISTS{}", before, after);
        }
    }
    // Same for CREATE INDEX and CREATE VIEW
    // ...
    sql.to_string()
}
```

**Why?** Prevents conflicts if tables already exist locally from previous sessions.

### Data Sync Strategy

```sql
-- For each table, fetch from remote
SELECT * FROM "users";

-- Get column info
PRAGMA table_info("users");
-- Returns: cid, name, type, notnull, dflt_value, pk

-- Build INSERT with all columns
INSERT OR IGNORE INTO "users" (id, name, email) VALUES (?, ?, ?);

-- Insert each row
-- OR IGNORE prevents conflicts if row already exists locally
```

**Why `INSERT OR IGNORE`?** If a row was already created locally (before sync), it won't be overwritten. This is a simple conflict resolution strategy — in production, you might want more sophisticated merging.

---

## Write Flow

### execute() Method

```
execute(sql, params):
│
├─► Execute on local_conn
│   └─► local_conn.execute(sql, params).await
│   └─► Returns rows_affected
│
├─► Queue operation
│   ├─► Create PendingOperation {
│   │       sql, params, Execute, SystemTime::now()
│   │   }
│   ├─► Save to libsql_pending_ops table
│   │   └─► INSERT INTO libsql_pending_ops (sql, params_json, ...)
│   └─► Push to pending_operations Vec
│
└─► If is_online():
    └─► Trigger sync_pending_operations()
```

### Code

```rust
pub fn execute(
    &self,
    sql: &str,
    parameters: Option<QueryParameters>,
) -> Result<u64, PhpException> {
    let params = parameters.map(|p| p.to_params());

    // 1. Execute locally (always succeeds)
    let local_result = runtime()
        .block_on(async {
            self.local_conn
                .execute(sql, params.clone().unwrap_or(libsql::params::Params::None))
                .await
        })
        .map_err(|e| PhpException::from(format!("{:?}", e)))?;

    // 2. Queue for sync
    self.queue_operation(sql, params, OperationType::Execute)
        .map_err(|e| PhpException::from(format!("{:?}", e)))?;

    Ok(local_result)
}
```

### execute_batch() Method

```rust
pub fn execute_batch(&self, sql: &str) -> Result<bool, PhpException> {
    // 1. Execute locally
    runtime()
        .block_on(async { self.local_conn.execute_batch(sql).await })
        .map_err(|e| PhpException::from(format!("{:?}", e)))?;

    // 2. Queue for sync
    self.queue_operation(sql, None, OperationType::ExecuteBatch)
        .map_err(|e| PhpException::from(format!("{:?}", e)))?;

    Ok(true)
}
```

### queue_operation() Method

```rust
fn queue_operation(
    &self,
    sql: &str,
    params: Option<libsql::params::Params>,
    op_type: OperationType,
) -> Result<(), libsql::Error> {
    // 1. Create pending operation
    let mut pending_op = PendingOperation {
        id: None,
        sql: sql.to_string(),
        params: params.unwrap_or(libsql::params::Params::None),
        operation_type: op_type,
        timestamp: std::time::SystemTime::now(),
    };

    // 2. Save to database (persistent queue)
    pending_op.id = Some(self.save_pending_operation(&pending_op));

    // 3. Add to in-memory queue
    self.pending_operations.lock().unwrap().push(pending_op);

    // 4. If online, try to sync
    if self.is_online() {
        let _ = self.sync_pending_operations();
    }

    Ok(())
}
```

---

## Parameter Serialization

Pending operations store parameters as JSON in the database.

### libsql::Value → JSON

```rust
fn libsql_value_to_json(value: &Value) -> JsonValue {
    match value {
        Value::Null => JsonValue::Null,
        Value::Integer(i) => JsonValue::Number(Number::from(*i)),
        Value::Real(f) => Number::from_f64(*f)
            .map(JsonValue::Number)
            .unwrap_or(JsonValue::Null),
        Value::Text(s) => JsonValue::String(s.clone()),
        Value::Blob(b) => JsonValue::Array(
            b.iter().map(|v| JsonValue::Number(Number::from(*v))).collect(),
        ),
    }
}
```

### JSON → libsql::Value

```rust
fn json_to_libsql_value(json: &JsonValue) -> Value {
    match json {
        JsonValue::Null => Value::Null,
        JsonValue::Number(n) if n.is_i64() => Value::Integer(n.as_i64().unwrap()),
        JsonValue::Number(n) if n.is_f64() => Value::Real(n.as_f64().unwrap()),
        JsonValue::String(s) => Value::Text(s.clone()),
        JsonValue::Array(arr) => Value::Blob(
            arr.iter().filter_map(|v| v.as_u64().map(|n| n as u8)).collect(),
        ),
        _ => Value::Null,
    }
}
```

### Storage Format

**Positional params:**
```json
[1, "Alice", 30, null]
```

**Named params:**
```json
{":name": "Alice", ":age": 30}
```

---

## Sync Flow

### sync_pending_operations()

```
sync_pending_operations():
│
├─► For each op in pending_operations:
│   │
│   ├─► If ExecuteBatch:
│   │   └─► remote_conn.execute_batch(&op.sql).await
│   │
│   ├─► If Execute:
│   │   └─► remote_conn.execute(&op.sql, op.params).await
│   │
│   ├─► If success:
│   │   ├─► DELETE FROM libsql_pending_ops WHERE id = op.id
│   │   └─► Remove from pending_operations Vec
│   │
│   └─► If failure:
│       └─► Keep in queue (retry later)
│
└─► Return sync status
```

### Manual Sync (PHP-facing)

```rust
pub fn manual_sync(&self) -> Result<String, String> {
    // Check connectivity
    if !self.check_connectivity() {
        return Err("Remote server is not reachable".to_string());
    }

    // Initial sync if needed
    let _ = self.initial_sync_if_needed();

    // Sync pending operations
    let synced = self.sync_pending_operations();

    Ok(format!("Synced {} operations", synced))
}
```

### PHP Usage

```php
// Manual sync with error handling
try {
    $db->sync();
    echo "Synced successfully";
} catch (Exception $e) {
    $pending = $db->getPendingOperationsCount();
    echo "Sync failed. $pending operations still pending.";
}

// Check sync status
echo $db->getPendingOperationsCount();  // Number of unsynced operations
```

---

## Connectivity Checking

### is_online() with Caching

```rust
pub fn is_online(&self) -> bool {
    // Static cache across all calls
    static LAST_CHECK: Mutex<Option<(Instant, bool)>> = Mutex::new(None);

    let mut last_check = LAST_CHECK.lock().unwrap();

    // Return cached value if less than 5 seconds old
    if let Some((time, status)) = *last_check {
        if time.elapsed() < Duration::from_secs(5) {
            return status;
        }
    }

    // Fresh check
    let current_status = crate::utils::runtime::is_reachable(&self.remote_url);
    *last_check = Some((Instant::now(), current_status));
    current_status
}
```

### is_reachable() Implementation

```rust
pub fn is_reachable(url: &str) -> bool {
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

    // Even 404 means server is reachable
    match client.get(&transformed_url).send() {
        Ok(_) => true,
        _ => false,
    }
}
```

**URL transformation:** The `format_url()` function normalizes URLs:
- `http://my-org.turso.io/v1/...` → `http://turso.io/v2`
- `http://localhost:8080` → `http://localhost/v2`
- Extracts domain, sets path to `/v2`

### check_connectivity() vs is_online()

| Method | Behavior | Caching |
|--------|----------|---------|
| `is_online()` | Returns cached status | 5-second TTL |
| `check_connectivity()` | Fresh HTTP check | No cache, updates `is_online` |

```php
// Cached (fast, may be stale)
$db->isOnline();

// Fresh (slower, accurate)
$db->checkConnectivity();
```

---

## Pending Operation Management

### save_pending_operation()

```rust
fn save_pending_operation(&self, op: &PendingOperation) -> i64 {
    // Serialize params to JSON
    let params_json = match &op.params {
        libsql::params::Params::None => JsonValue::Null,
        libsql::params::Params::Positional(vec) => {
            JsonValue::Array(vec.iter().map(libsql_value_to_json).collect())
        }
        libsql::params::Params::Named(map) => {
            let mut json_map = Map::new();
            for (k, v) in map {
                json_map.insert(k.clone(), libsql_value_to_json(v));
            }
            JsonValue::Object(json_map)
        }
    };

    let params_str = serde_json::to_string(&params_json).unwrap();
    let op_type = match op.operation_type {
        OperationType::Execute => "Execute",
        OperationType::ExecuteBatch => "ExecuteBatch",
    };
    let timestamp = op.timestamp
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap()
        .as_secs() as i64;

    let result = runtime().block_on(async {
        self.local_conn.execute(
            "INSERT INTO libsql_pending_ops (sql, params_json, operation_type, timestamp) VALUES (?, ?, ?, ?)",
            libsql::params![op.sql.clone(), params_str.clone(), op_type, timestamp],
        ).await
    });

    match result {
        Ok(_) => self.local_conn.last_insert_rowid(),  // Return new ID
        Err(e) => {
            log_error_to_tmp(&format!("Failed to save pending operation: {}", e));
            -1
        }
    }
}
```

### remove_pending_operation()

```rust
fn remove_pending_operation(&self, id: i64) {
    let _ = runtime().block_on(async {
        self.local_conn
            .execute("DELETE FROM libsql_pending_ops WHERE id = ?", libsql::params![id])
            .await
    }).map_err(|err| {
        log_error_to_tmp(&format!("Failed to remove pending operation: {}", err));
    });
}
```

### load_pending_operations()

```rust
fn load_pending_operations(&self) {
    let mut ops = self.pending_operations.lock().unwrap();
    ops.clear();

    let query_result = runtime().block_on(async {
        self.local_conn
            .query("SELECT id, sql, params_json, operation_type, timestamp FROM libsql_pending_ops", libsql::params![])
            .await
    });

    if let Ok(mut rows) = query_result {
        while let Ok(Some(row)) = runtime().block_on(rows.next()) {
            let id: i64 = row.get(0).unwrap();
            let sql: String = row.get(1).unwrap();
            let params_json: String = row.get(2).unwrap();
            let op_type: String = row.get(3).unwrap();
            let timestamp: i64 = row.get(4).unwrap();

            // Deserialize params
            let json_value: JsonValue = serde_json::from_str(&params_json).unwrap_or(JsonValue::Null);
            let params = match json_value {
                JsonValue::Array(items) => libsql::params::Params::Positional(
                    items.iter().map(json_to_libsql_value).collect(),
                ),
                JsonValue::Object(map) => libsql::params::Params::Named(
                    map.iter().map(|(k, v)| (k.clone(), json_to_libsql_value(v))).collect(),
                ),
                _ => libsql::params::Params::None,
            };

            let operation_type = match op_type.as_str() {
                "ExecuteBatch" => OperationType::ExecuteBatch,
                _ => OperationType::Execute,
            };

            let timestamp = std::time::UNIX_EPOCH + std::time::Duration::from_secs(timestamp as u64);

            ops.push(PendingOperation {
                id: Some(id),
                sql,
                params,
                operation_type,
                timestamp,
            });
        }
    }
}
```

---

## Query Flow (Offline Mode)

### query() Method

```rust
pub fn query(
    &self,
    sql: &str,
    parameters: Option<QueryParameters>,
    force_remote: Option<bool>,
) -> Result<libsql::Rows, PhpException> {
    let force_remote = force_remote.unwrap_or(false);

    if force_remote && self.is_online() {
        // Query remote directly
        runtime().block_on(async {
            self.remote_conn.query(sql, params).await
        })
    } else {
        // Query local (default)
        runtime().block_on(async {
            self.local_conn.query(sql, params).await
        })
    }
}
```

**Key insight:** By default, queries always go to the local database. The `force_remote` parameter (exposed in PHP as the 3rd argument to `query()`) allows reading from the remote when needed.

```php
// Local query (default)
$result = $db->query("SELECT * FROM notes");

// Force remote (for sqld offline mode)
$result = $db->query("SELECT * FROM notes", [], true);
```

---

## Edge Cases & Considerations

### 1. Process Restart

If the PHP process restarts:
- ✅ Pending operations persist in `libsql_pending_ops` table
- ✅ On next connection, `load_pending_operations()` restores the queue
- ✅ `is_online` cache resets (fresh check on next call)

### 2. Conflict Resolution

Current strategy is **simple but not perfect**:
- Schema: `CREATE IF NOT EXISTS` — skips if exists
- Data: `INSERT OR IGNORE` — skips if primary key exists

**Potential issues:**
- Updates/deletes on remote that happened while offline may conflict
- No merge logic — last write wins at the row level
- No conflict callbacks for application-level resolution

### 3. Large Queues

If many operations queue up while offline:
- Each operation is a separate row in `libsql_pending_ops`
- Sync iterates sequentially — no batching
- Failed operations stay in queue indefinitely

**Improvement opportunities:**
- Batch sync for operations on the same table
- TTL for old operations
- Conflict detection before sync attempt

### 4. Thread Safety

`pending_operations` and `is_online` use `Arc<Mutex<T>>`:
- Safe for concurrent access within a single process
- PHP's request model (one request = one process) means this is rarely an issue
- In CLI long-running processes, mutex prevents race conditions

### 5. Memory vs Persistence

Operations are stored **both** in memory (`Vec<PendingOperation>`) and on disk (`libsql_pending_ops` table):
- Memory: Fast iteration during sync
- Disk: Persistence across restarts

**Trade-off:** Double storage, but ensures durability.
