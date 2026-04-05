# Connection Modes: Complete Reference

## Overview

The extension supports **5 distinct connection modes**, each implemented as an isolated provider module. Mode selection happens automatically during `LibSQL::__construct()` based on the configuration provided.

---

## Mode Comparison Matrix

| Mode | Provider | Local DB | Remote DB | Sync | Offline Writes | Use Case |
|------|----------|----------|-----------|------|----------------|----------|
| **local** | `local.rs` | ✅ File/ memory | ❌ | ❌ | ❌ | Standard SQLite usage |
| **remote** | `remote.rs` | ❌ | ✅ HTTP | ❌ | ❌ | Cloud-only access |
| **remote_replica** | `remote_replica.rs` | ✅ Cache | ✅ Primary | ✅ Auto | ❌ | Read scaling, edge |
| **offline_write** (Turso) | `offline_write.rs` | ✅ Primary | ✅ Cloud | ✅ Auto | ✅ | Offline-first (Turso Cloud) |
| **sqld_offline_write** | `sqld_offline_write.rs` | ✅ Primary | ✅ Self-hosted | ✅ Manual | ✅ | Offline-first (self-hosted) |

---

## 1. Local Mode

### File: `src/providers/local.rs`

### Purpose
Connect to a local SQLite-compatible libSQL database file or in-memory database.

### Implementation

```rust
pub fn create_local_connection(
    url: String,
    flags: Option<i32>,
    encryption_key: Option<String>,
) -> Result<libsql::Connection, PhpException> {
    runtime().block_on(async {
        // Parse flags
        let db_flags = match flags {
            Some(LIBSQL_OPEN_READONLY) => libsql::OpenFlags::SQLITE_OPEN_READ_ONLY,
            Some(LIBSQL_OPEN_READWRITE) => libsql::OpenFlags::SQLITE_OPEN_READ_WRITE,
            Some(LIBSQL_OPEN_CREATE) => libsql::OpenFlags::SQLITE_OPEN_CREATE,
            Some(5) => libsql::OpenFlags::SQLITE_OPEN_READ_ONLY | libsql::OpenFlags::SQLITE_OPEN_CREATE,
            _ => libsql::OpenFlags::default(),
        };

        // Optional encryption
        let encryption_config = if let Some(key) = encryption_key {
            Some(libsql::EncryptionConfig::new(
                libsql::Cipher::Aes256Cbc,
                key.as_bytes().to_vec().into(),
            ))
        } else {
            None
        };

        // Build and connect
        let db = libsql::Builder::new_local(url)
            .flags(db_flags)
            .encryption_config(encryption_config.unwrap())
            .build()
            .await
            .map_err(|e| PhpException::default(format!("Database build failed: {}", e)))?;

        db.connect()
            .map_err(|e| PhpException::default(format!("Connection failed: {}", e)))
    })
}
```

### Flag Combinations

| Flags | Value | Meaning |
|-------|-------|---------|
| `OPEN_READONLY` | 1 | Read-only access |
| `OPEN_READWRITE` | 2 | Read-write access |
| `OPEN_CREATE` | 4 | Create if not exists |
| `OPEN_READWRITE \| OPEN_CREATE` | 6 | **Default** — read-write + create |
| `OPEN_READONLY \| OPEN_CREATE` | 5 | Read-only + create (special case) |

### PHP Usage

```php
// File database (3 equivalent forms)
$db = new LibSQL("database.db");
$db = new LibSQL("file:database.db");
$db = new LibSQL("libsql:dbname=database.db");

// In-memory
$db = new LibSQL(":memory:");

// With encryption
$db = new LibSQL("file:encrypted.db", false, 6, "my-secret-key");

// Read-only
$db = new LibSQL("file:readonly.db", false, LibSQL::OPEN_READONLY);
```

### Detection Triggers
- URL starts with `file:`
- URL ends with `.db`
- URL contains `:memory:`
- URL starts with `libsql:` (but no authToken)

---

## 2. Remote Mode

### File: `src/providers/remote.rs`

### Purpose
Connect to a remote libSQL server (Turso Cloud or self-hosted sqld) via HTTP/HTTPS.

### Implementation

```rust
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
```

**Note:** Uses `.unwrap()` — will panic on failure instead of returning a graceful error. This is a known inconsistency.

### PHP Usage

```php
// DSN format
$db = new LibSQL("libsql:dbname=https://my-db.turso.io;authToken=tok_abc");
$db = new LibSQL("libsql:dbname=libsql://my-db.turso.io;authToken=tok_abc");

// Config array format
$config = [
    "url" => "https://my-db.turso.io",
    "authToken" => "tok_abc",
];
$db = new LibSQL($config);
```

### Detection Triggers
- URL starts with `libsql://` or `http://` or `https://`
- Auth token is non-empty
- No syncUrl provided (otherwise it becomes `remote_replica`)

### Characteristics
- **All operations go through HTTP** — no local cache
- **Network required** — fails if offline
- **No sync needed** — direct connection to remote
- **Read and write** — full access

---

## 3. Remote Replica Mode

### File: `src/providers/remote_replica.rs`

### Purpose
Maintain a local cache of a remote database with automatic synchronization. Ideal for read-heavy workloads and edge computing.

### Implementation

```rust
pub fn create_remote_replica_connection(
    url: String,              // Local file path for replica
    auth_token: String,       // Auth for remote
    sync_url: String,         // Remote sync endpoint
    sync_interval: std::time::Duration,  // Auto-sync interval
    read_your_writes: bool,   // Consistency guarantee
    encryption_key: Option<String>,
) -> (libsql::Database, libsql::Connection) {
    let (db, conn) = runtime().block_on(async {
        let encryption_config = if let Some(key) = encryption_key {
            Some(libsql::EncryptionConfig::new(
                libsql::Cipher::Aes256Cbc,
                key.as_bytes().to_vec().into(),
            ))
        } else {
            None
        };

        let db = libsql::Builder::new_remote_replica(url, sync_url, auth_token)
            .encryption_config(encryption_config.unwrap())
            .read_your_writes(read_your_writes)
            .sync_interval(sync_interval)
            .build()
            .await
            .unwrap();
        let conn = db.connect().unwrap();
        (db, conn)
    });

    (db, conn)  // Returns both DB (for sync) and Connection (for queries)
}
```

### Key Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| `url` | String | — | Local file path for the replica cache |
| `authToken` | String | — | JWT for remote authentication |
| `syncUrl` | String | — | Remote endpoint URL |
| `syncInterval` | Duration | 5s | Auto-sync frequency |
| `read_your_writes` | bool | true | Ensure local reads see recent writes |
| `encryptionKey` | String | "" | Database encryption key |

### PHP Usage

```php
$config = [
    "url" => "file:database.db",
    "authToken" => "tok_abc",
    "syncUrl" => "https://my-db.turso.io",
    "syncInterval" => 5,
    "read_your_writes" => true,
    "encryptionKey" => ""
];

$db = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: false  // ← Key: false = standard replica
);

// Manual sync (also happens automatically on interval)
$db->sync();
```

### Detection Triggers
- URL is local (`file:` or `.db`)
- Auth token is non-empty
- Sync URL is provided (`http://`, `https://`, or `libsql://`)

### How Sync Works

```
┌─────────────────────────────────────────────────┐
│                  Local Replica                   │
│  ┌─────────────┐         ┌──────────────────┐   │
│  │  Local DB   │◄───────►│  Sync Engine     │   │
│  │  (file.db)  │         │  (auto/manual)   │   │
│  └─────────────┘         └────────┬─────────┘   │
└───────────────────────────────────┼─────────────┘
                                    │ HTTP
                                    ▼
                          ┌─────────────────┐
                          │  Remote Primary │
                          │  (Turso Cloud)  │
                          └─────────────────┘
```

1. **Auto-sync**: Background process syncs every `syncInterval` seconds
2. **Manual sync**: `$db->sync()` forces immediate synchronization
3. **Read-your-writes**: When enabled, local reads reflect recent local writes

### Characteristics
- **Reads are local** — fast, no network needed
- **Writes go to local** — then sync to remote
- **Conflict resolution** — libSQL handles conflicts during sync
- **Best for** — read-heavy workloads, edge deployments

---

## 4. Offline Write Mode (Turso Cloud)

### File: `src/providers/offline_write.rs`

### Purpose
Write to a local database first, then automatically sync to Turso Cloud when online. This is the **Turso Cloud variant** of offline-first.

### Implementation

```rust
pub fn create_offline_write_connection(
    db_path: String,
    auth_token: String,
    sync_url: String,
) -> (libsql::Database, libsql::Connection) {
    let (db, conn) = runtime().block_on(async {
        let db = libsql::Builder::new_synced_database(db_path, sync_url, auth_token)
            .build()
            .await
            .unwrap();
        let conn = db.connect().unwrap();
        (db, conn)
    });
    (db, conn)
}
```

**Note:** This is a thin wrapper around libSQL's `new_synced_database` builder. The heavy lifting (queueing, sync) is done by libSQL internally.

### PHP Usage

```php
$config = [
    "url" => "file:database.db",
    "authToken" => "tok_abc",
    "syncUrl" => "https://my-db.turso.io",
];

$db = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true  // ← Key: true = offline write mode
);

// Write works even when offline
$db->execute("INSERT INTO notes (content) VALUES (?)", ["Hello"]);

// Sync when online
try {
    $db->sync();
} catch (Exception $e) {
    echo "Will sync later";
}
```

### Detection Triggers
- Same config as `remote_replica`
- `offline_writes: true` parameter passed to constructor

### Characteristics
- **Writes always succeed** — stored locally first
- **Auto-sync** — libSQL handles syncing internally
- **Turso Cloud only** — uses libSQL's built-in synced database
- **Simpler than sqld_offline_write** — less custom logic

---

## 5. sqld Offline Write Mode (Self-Hosted)

### File: `src/providers/sqld_offline_write.rs`

### Purpose
The **most complex mode**. Full offline-first support with self-hosted sqld servers, including:
- Dual connection (local + remote)
- Pending operation queue
- Initial schema sync
- Connectivity checking with caching
- Manual sync with conflict handling

### Architecture

```
┌─────────────────────────────────────────────────────────────┐
│              OfflineWriteConnection                          │
│                                                              │
│  ┌──────────────────┐          ┌──────────────────────┐     │
│  │   local_conn     │          │    remote_conn       │     │
│  │  (libsql::Conn)  │          │   (libsql::Conn)     │     │
│  │                  │          │                      │     │
│  │  Always writable │          │  Used when online    │     │
│  │  Stores pending  │          │  For sync & queries  │     │
│  │  ops table       │          │  (if force_remote)   │     │
│  └────────┬─────────┘          └──────────┬───────────┘     │
│           │                               │                  │
│           ▼                               ▼                  │
│  ┌────────────────────────────────────────────────────┐     │
│  │              pending_operations                     │     │
│  │         Arc<Mutex<Vec<PendingOperation>>>           │     │
│  │                                                     │     │
│  │  Each PendingOperation:                             │     │
│  │  - id: Option<i64>                                 │     │
│  │  - sql: String                                     │     │
│  │  - params: libsql::params::Params                  │     │
│  │  - operation_type: Execute | ExecuteBatch           │     │
│  │  - timestamp: SystemTime                           │     │
│  └────────────────────────────────────────────────────┘     │
│                                                              │
│  ┌────────────────────────────────────────────────────┐     │
│  │              is_online                              │     │
│  │         Arc<Mutex<bool>> (5-second cache)           │     │
│  └────────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────────┘
```

### Internal Tables

When initialized, the local database gets two special tables:

```sql
-- Tracks pending operations
CREATE TABLE IF NOT EXISTS libsql_pending_ops (
    id INTEGER PRIMARY KEY,
    sql TEXT NOT NULL,
    params_json TEXT NOT NULL,
    operation_type TEXT NOT NULL,
    timestamp INTEGER NOT NULL
);

-- Tracks initialization state
CREATE TABLE IF NOT EXISTS libsql_metadata (
    key TEXT PRIMARY KEY,
    value TEXT
);
```

**Metadata keys:**
| Key | Value | Purpose |
|-----|-------|---------|
| `schema_initialized` | `"true"` | Prevents re-creating tables on restart |
| `initial_sync_done` | `"true"` | Tracks if initial remote sync completed |

### Initialization Flow

```
1. Create local_conn (local provider with flags + encryption)
2. Check if already initialized (schema_initialized in metadata)
3. If not initialized:
   a. Set PRAGMA synchronous = NORMAL
   b. Set PRAGMA journal_mode = WAL
   c. Create libsql_pending_ops table
   d. Create libsql_metadata table
   e. Set schema_initialized = true
4. Create remote_conn (remote provider)
5. Check if remote is reachable → set is_online
6. If online: run initial_sync_if_needed()
7. Load any pending operations from libsql_pending_ops
```

### Initial Sync Flow

```
initial_sync_if_needed():
1. Check initial_sync_done in metadata
2. If not done:
   a. Fetch schema from remote (sqlite_master WHERE type IN ('table','index','view'))
   b. Apply schema locally (with IF NOT EXISTS added)
   c. Fetch all table names from remote
   d. For each table:
      - Fetch all rows from remote
      - Insert locally (INSERT OR IGNORE)
   e. Set initial_sync_done = true
```

### Write Flow

```
execute(sql, params):
1. Execute on local_conn → returns rows affected
2. Queue operation:
   a. Create PendingOperation { sql, params, Execute, now() }
   b. Save to libsql_pending_ops table
   c. Push to pending_operations Vec
3. If is_online():
   → Trigger sync_pending_operations()
```

### Sync Flow

```
sync_pending_operations():
1. For each pending_op in pending_operations:
   a. If ExecuteBatch:
      → remote_conn.execute_batch(&op.sql).await
   b. If Execute:
      → remote_conn.execute(&op.sql, op.params).await
   c. If success:
      → Remove from libsql_pending_ops table
      → Remove from pending_operations Vec
   d. If failure:
      → Keep in queue (will retry later)
```

### Connectivity Check

```rust
pub fn is_online(&self) -> bool {
    // Cache status for 5 seconds
    static LAST_CHECK: Mutex<Option<(Instant, bool)>> = Mutex::new(None);

    let mut last_check = LAST_CHECK.lock().unwrap();
    if let Some((time, status)) = *last_check {
        if time.elapsed() < Duration::from_secs(5) {
            return status;  // Return cached value
        }
    }

    let current_status = crate::utils::runtime::is_reachable(&self.remote_url);
    *last_check = Some((Instant::now(), current_status));
    current_status
}
```

**Why cache?** Avoids making HTTP requests on every call. The 5-second TTL balances responsiveness with network overhead.

### PHP Usage

```php
$config = [
    "url" => "file:sqld-offline-write.db",
    "authToken" => "your_jwt_token",
    "syncUrl" => "http://your-sqld-server.com:8080"
];

$db = new LibSQL(
    config: $config,
    sqld_offline_mode: true,   // ← Key: enables sqld offline mode
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true
);

// Write (always works, even offline)
$db->execute("INSERT INTO notes (content) VALUES (?)", ["Note 1"]);

// Check status
$db->isOnline();                    // bool (cached, 5s TTL)
$db->checkConnectivity();           // bool (fresh check)
$db->getPendingOperationsCount();   // int (queue size)

// Manual sync
try {
    $db->sync(true);  // true = log result
} catch (Exception $e) {
    echo "Will sync later";
}

// Query (always local by default)
$result = $db->query("SELECT * FROM notes");

// Query from remote (force_remote)
$result = $db->query("SELECT * FROM notes", [], true);
```

### Detection Triggers
- Same config as `remote_replica`
- `sqld_offline_mode: true` AND `offline_writes: true`

### Characteristics
- **Full offline support** — works without network
- **Operation queue** — persists across restarts (stored in local DB)
- **Manual sync** — you control when to sync
- **Self-hosted** — works with any sqld instance
- **Most complex** — 800+ lines of custom logic

---

## Mode Selection Summary

```
Constructor called with config
        │
        ▼
┌───────────────────────────────────┐
│  Parse config (DSN or Array)      │
│  Extract: url, authToken, syncUrl │
└───────────────┬───────────────────┘
                │
                ▼
┌───────────────────────────────────┐
│  sqld_offline_mode = true?        │
│  AND offline_writes = true?       │
│  ── YES ──► sqld_offline_write     │
└───────────────┬───────────────────┘
                │ NO
                ▼
┌───────────────────────────────────┐
│  offline_writes = true?           │
│  AND has authToken + syncUrl?     │
│  ── YES ──► offline_write (Turso) │
└───────────────┬───────────────────┘
                │ NO
                ▼
┌───────────────────────────────────┐
│  get_mode(url, authToken, syncUrl)│
│                                   │
│  file:/.db + authToken + syncUrl  │
│  ──► remote_replica               │
│                                   │
│  libsql:// or http(s):// + token  │
│  ──► remote                       │
│                                   │
│  file: or .db or :memory:         │
│  ──► local                        │
│                                   │
│  Nothing matches                  │
│  ──► ERROR                        │
└───────────────────────────────────┘
```
