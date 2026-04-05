# 📘 Repository Analysis: Turso Client PHP

## Project Overview

**turso-client-php** is a **native PHP extension** written in **Rust** that provides PHP applications with access to **libSQL** — a fork of SQLite designed for edge computing, replication, and production workloads. It is community-driven and published under the MIT License.

### Tech Stack

| Layer | Technology |
|-------|-----------|
| **Extension Language** | Rust (via `ext-php-rs` FFI framework) |
| **PHP Versions** | 8.1 – 8.5 (TS & NTS) |
| **Database Engine** | libSQL (SQLite fork, v0.9.19) |
| **Async Runtime** | Tokio (single global `Runtime`) |
| **HTTP Client** | reqwest (blocking) |
| **Testing** | Pest PHP (PHPUnit-based) |
| **Build System** | Cargo + Makefile + Docker Compose |
| **Platforms** | Linux, macOS, Windows/WSL, ARM64 |

### Purpose

This project bridges PHP with libSQL's advanced capabilities:
- **Local** SQLite-compatible databases
- **Remote** cloud database connections (Turso Cloud)
- **Remote Replica** with sync & offline writes
- **Embedded Replica** for edge/local-first architectures
- **sqld Offline Writes** for self-hosted libSQL servers

---

# 🧠 System Architecture

## High-Level Architecture

```
┌─────────────────────────────────────────────────────┐
│                     PHP Application                  │
│              (new LibSQL(), ->query(), etc.)         │
└──────────────────────┬──────────────────────────────┘
                       │  ext-php-rs FFI Bridge
                       ▼
┌─────────────────────────────────────────────────────┐
│              Rust Native Extension                   │
│                                                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │  LibSQL  │  │Transaction│  │  LibSQLStatement │  │
│  │  (Class) │  │  (Class)  │  │    (Class)       │  │
│  └────┬─────┘  └────┬─────┘  └────────┬─────────┘  │
│       │              │                 │             │
│       ▼              ▼                 ▼             │
│  ┌──────────────────────────────────────────────┐   │
│  │          Connection Registries                │   │
│  │  (Mutex<HashMap<String, Connection>>)        │   │
│  │  - CONNECTION_REGISTRY                        │   │
│  │  - OFFLINE_CONNECTION_REGISTRY                │   │
│  │  - TRANSACTION_REGISTRY                       │   │
│  │  - STATEMENT_REGISTRY                         │   │
│  └──────────────────────┬───────────────────────┘   │
│                         │                           │
│       ┌─────────────────┼─────────────────┐        │
│       ▼                 ▼                 ▼         │
│  ┌────────┐      ┌────────────┐   ┌──────────────┐ │
│  │ local  │      │   remote   │   │remote_replica│ │
│  │provider│      │  provider  │   │  provider    │ │
│  └────────┘      └────────────┘   └──────────────┘ │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │          OfflineWriteConnection               │   │
│  │  (local_conn + remote_conn + pending_ops)    │   │
│  └──────────────────────────────────────────────┘   │
│                                                     │
│  ┌──────────────────────────────────────────────┐   │
│  │              Utils Module                     │   │
│  │  - runtime (Tokio)                            │   │
│  │  - query_params (PHP→libsql conversion)       │   │
│  │  - result_set (libsql→PHP conversion)         │   │
│  │  - config_value (PHP config parsing)          │   │
│  └──────────────────────────────────────────────┘   │
└──────────────────────┬──────────────────────────────┘
                       │  libsql crate (Rust)
                       ▼
┌─────────────────────────────────────────────────────┐
│                    libSQL Engine                     │
│         (local file / remote HTTP / replica)         │
└─────────────────────────────────────────────────────┘
```

## Data Flow

### 1. Connection Creation Flow
```
PHP: new LibSQL("file:my.db") 
  → ConfigValue::from_zval() parses PHP string/array
  → parse_dsn() / config array extraction
  → get_mode() determines: local | remote | remote_replica
  → Provider creates libsql::Connection via Tokio runtime
  → Connection stored in CONNECTION_REGISTRY with UUID
  → LibSQL PHP object returned with conn_id
```

### 2. Query Execution Flow
```
PHP: $db->query("SELECT * FROM users WHERE id = ?", [1])
  → QueryParameters::from_zval() converts PHP array
  → hooks::use_query::query() looks up conn by conn_id
  → runtime().block_on(async { conn.query(sql, params) })
  → Rows collected into ResultSet struct
  → ResultSet.into_zval() converts to PHP array
  → LibSQLResult object returned for fetchArray/fetchSingle
```

### 3. Offline Write Flow
```
PHP: new LibSQL($config, offline_writes: true)
  → OfflineWriteConnection::new()
    → Creates local_conn (local provider)
    → Creates remote_conn (remote provider)
    → Checks initial sync status
    → Loads pending operations from local DB
  → execute() writes to local_conn immediately
  → Operation queued in pending_operations Vec
  → If online, sync_pending_operations() flushes to remote
```

---

# ⚙️ Core Components

## Module Breakdown

### 1. `src/lib.rs` — Main Extension Entry Point
- **Defines the `LibSQL` PHP class** with `#[php_class]` macro
- **Global registries**: `CONNECTION_REGISTRY`, `OFFLINE_CONNECTION_REGISTRY`, `TRANSACTION_REGISTRY`, `STATEMENT_REGISTRY` (all `Mutex<HashMap<String, T>>`)
- **Constants**: `LIBSQL_OPEN_READONLY`, `LIBSQL_ASSOC`, `LIBSQL_NUM`, etc.
- **Mode detection**: Parses DSN/config to determine connection mode
- **Delegates** all operations to `hooks::`, `providers::`, and `utils::` modules

### 2. `src/providers/` — Connection Providers

| File | Purpose |
|------|---------|
| `local.rs` | Local file/in-memory databases via `libsql::Builder::new_local()` |
| `remote.rs` | Cloud connections via `libsql::Builder::new_remote()` |
| `remote_replica.rs` | Replica with sync via `libsql::Builder::new_remote_replica()` |
| `offline_write.rs` | Synced database with offline writes via `libsql::Builder::new_synced_database()` |
| `sqld_offline_write.rs` | **Most complex**: Dual local+remote connection with pending operation queue, initial sync, connectivity checking, and automatic sync when online |

### 3. `src/hooks/` — Operation Handlers

| File | Purpose |
|------|---------|
| `use_exec.rs` | Execute single SQL statement |
| `use_exec_batch.rs` | Execute batch SQL statements |
| `use_query.rs` | Execute query and return ResultSet |
| `changes.rs` | Get rows affected count |
| `is_autocommit.rs` | Check autocommit status |
| `close.rs` | Close/remove connection from registry |
| `version.rs` | Get libSQL version string |
| `load_extensions.rs` | Load SQLite extensions |

### 4. `src/utils/` — Utilities

| File | Purpose |
|------|---------|
| `runtime.rs` | **Global Tokio runtime** (OnceCell), DSN parsing, mode detection, value conversion (libsql→PHP Zval), URL reachability check, webhook sender |
| `query_params.rs` | `QueryParameters` struct with `FromZval` impl — converts PHP arrays to `libsql::params::Params` |
| `config_value.rs` | `ConfigValue` enum — parses PHP string or array config |
| `result_set.rs` | `ResultSet` struct for query results |
| `log_error.rs` | Error logging to temp files |

### 5. `src/transaction.rs` — Transaction Management
- `LibSQLTransaction` PHP class
- Supports `DEFERRED`, `IMMEDIATE` (WRITE), `READONLY` (READ) behaviors
- `commit()` and `rollback()` remove transaction from `TRANSACTION_REGISTRY`

### 6. `src/statement.rs` — Prepared Statements
- `LibSQLStatement` PHP class
- Named (`:name`, `@name`, `$name`) and positional (`?`, `$1`, `@1`) parameter binding
- Parameter auto-detection from SQL string patterns
- `execute()`, `query()`, `reset()`, `finalize()`, `columns()`

### 7. `src/result.rs` — Query Results
- `LibSQLResult` PHP class
- Fetch modes: `LIBSQL_ASSOC`, `LIBSQL_NUM`, `LIBSQL_BOTH`, `LIBSQL_ALL`, `LIBSQL_LAZY`
- `LIBSQL_LAZY` returns `LibSQLIterator` (PHP Traversable)
- Separate offline mode handling (`__construct_offline`, `fetch_array_offline`)

### 8. `src/generator.rs` — Lazy Iterator
- `LibSQLIterator` PHP class implementing Traversable
- Wraps PHP array in `Rc<Zval>` with counter-based iteration

---

# 🚀 Features Breakdown

## Feature 1: Multiple Connection Modes

### How It Works
1. **DSN Parsing**: `parse_dsn()` in `runtime.rs` handles:
   - `file:database.db` → local
   - `libsql:dbname=https://...;authToken=...` → remote
   - Plain `database.db` → local (SQLite compatible)

2. **Config Array**: Alternative PHP array format:
   ```php
   ["url" => "file:db.db", "authToken" => "...", "syncUrl" => "..."]
   ```

3. **Mode Detection** (`get_mode()`):
   - `file:` + auth_token + sync_url → `remote_replica`
   - `libsql://` or `http://` + auth_token → `remote`
   - `file:` or `:memory:` → `local`

### Provider Selection
```rust
match mode.as_str() {
    "local" => providers::local::create_local_connection(...)
    "remote" => providers::remote::create_remote_connection(...)
    "remote_replica" => {
        if offline_writes {
            providers::offline_write::create_offline_write_connection(...)
        } else {
            providers::remote_replica::create_remote_replica_connection(...)
        }
    }
}
```

## Feature 2: Offline Writes (sqld mode)

### Architecture
The `OfflineWriteConnection` struct maintains:
- `local_conn`: Local libsql::Connection (always writable)
- `remote_conn`: Remote libsql::Connection (for sync)
- `is_online`: `Arc<Mutex<bool>>` — cached connectivity status (5-second TTL)
- `pending_operations`: `Arc<Mutex<Vec<PendingOperation>>>` — queue of unsynced operations

### Operation Flow
1. **Write**: `execute()` writes to `local_conn` immediately
2. **Queue**: Operation saved to `libsql_pending_ops` table + pushed to Vec
3. **Auto-sync**: If `is_online()`, triggers `sync_pending_operations()`
4. **Manual sync**: `manual_sync()` iterates pending ops, executes on remote, removes on success

### Initial Sync
When first connecting, `initial_sync_if_needed()`:
1. Fetches schema from remote (`sqlite_master`)
2. Applies schema locally with `IF NOT EXISTS`
3. Fetches all table data from remote
4. Inserts locally with `INSERT OR IGNORE`
5. Marks `initial_sync_done` in metadata

## Feature 3: Transactions

### Transaction Behaviors
| Behavior | libSQL Mapping | Description |
|----------|---------------|-------------|
| `DEFERRED` (default) | `TransactionBehavior::Deferred` | Lock acquired on first read/write |
| `WRITE` / `IMMEDIATE` | `TransactionBehavior::Immediate` | Lock acquired immediately |
| `READ` / `READONLY` | `TransactionBehavior::ReadOnly` | Read-only transaction |

### Lifecycle
```php
$tx = $db->transaction("DEFERRED");  // Creates transaction, stores in TRANSACTION_REGISTRY
$tx->execute("INSERT INTO ...");      // Uses conn_id to execute
$tx->commit();                        // Removes from registry, commits
// or
$tx->rollback();                      // Removes from registry, rolls back
```

## Feature 4: Prepared Statements

### Parameter Binding
- **Named**: `:name`, `@name`, `$name` — stored in `HashMap<String, String>`
- **Positional**: `?`, `?1`, `$1`, `@1` — auto-detected from SQL string
- **bind_named()**: Explicit named binding
- **bind_positional()**: Auto-detects placeholder style from `self.stmt`

### Execution
```php
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([1]);  // or $stmt->query([1]);
$stmt->reset();       // Clear params, reuse statement
$stmt->finalize();    // Remove from registry, free resources
```

## Feature 5: Result Fetching

### Fetch Modes
| Mode | Value | Output |
|------|-------|--------|
| `LIBSQL_ASSOC` | 1 | `[['name' => 'Alice'], ...]` |
| `LIBSQL_NUM` | 2 | `[[0 => 1, 1 => 'Alice'], ...]` |
| `LIBSQL_BOTH` | 3 | Both keys (default) |
| `LIBSQL_ALL` | 4 | Full ResultSet as array |
| `LIBSQL_LAZY` | 5 | `LibSQLIterator` (Traversable) |

### Methods
- `fetchArray(mode)` — Returns all rows
- `fetchSingle(mode)` — Returns first row only
- `columnName(index)`, `columnType(index)`, `numColumns()` — Column metadata

## Feature 6: Extension Loading

```php
$db->enableLoadExtension(true);           // Enable loading
$db->loadExtensions("/path/to/extension"); // Single
$db->loadExtensions(["/ext1", "/ext2"]);   // Multiple
```

## Feature 7: Webhook/Event Capture

```php
$db->captureIt("query_error", "SELECT ...", "Connection timeout");
```
Sends POST to `$cdc_url` with JSON: `{event_type, query, message}`

---

# 🧩 Developer Mental Model

## How to Think About This Codebase

### 1. Registry-Centric Design
All stateful objects (connections, transactions, statements) are stored in **global Mutex-protected HashMaps** keyed by UUID. PHP objects only hold the `conn_id`/`trx_id`/`stmt_id` string reference.

```
PHP Object (lightweight, just an ID)
    ↓
Global Registry (Mutex<HashMap<String, RealObject>>)
    ↓
Actual Rust Object (heavy, holds libsql resources)
```

**Implication**: When debugging, always check registry lookups first — "not found" errors mean the ID is wrong or the object was already removed.

### 2. Async-Sync Bridge
The extension uses a **single global Tokio runtime** (`OnceCell<Runtime>`) with `block_on()` for every async operation. This is simple but means:
- All database operations are synchronous from PHP's perspective
- No concurrent operations from a single PHP request
- The runtime is initialized lazily on first use

### 3. Provider Pattern
Each connection mode is isolated in its own provider module. The `LibSQL::__construct()` method:
1. Parses config → determines mode
2. Calls appropriate provider
3. Stores result in correct registry
4. Returns lightweight PHP object with ID

**To add a new mode**: Add a new provider file, update the `match mode` block, and add registry handling.

### 4. Error Handling Pattern
- Rust `Result<T, PhpException>` returned from all PHP-facing functions
- Errors logged to temp files via `log_error_to_tmp()`
- PHP exceptions created with `PhpException::default(message)` or `PhpException::from(message)`

### 5. Type Conversion Pipeline
```
PHP Zval → FromZval trait → Rust struct (QueryParameters, ConfigValue)
Rust struct (libsql::Value) → convert_libsql_value_to_zval() → PHP Zval
```

## Conventions & Rules

### Naming
- **Rust**: `snake_case` for functions, `PascalCase` for structs (but `#[php_class]` uses PHP naming)
- **PHP constants**: `LIBSQL_*` prefix
- **Registry keys**: UUID v4 strings

### Architecture Philosophy
- **Thin PHP layer**: PHP classes are minimal — just IDs and delegation
- **Thick Rust layer**: All business logic in Rust
- **No PHP-side state**: All state lives in Rust registries

### Code Style
- `#[php_class]` and `#[php_impl]` macros from `ext-php-rs`
- `Result<T, PhpException>` return types everywhere
- `runtime().block_on(async { ... })` pattern for all async calls
- Registry locking: `.lock().map_err(|e| PhpException::default(...))?`

---

# 📦 Usage Guide

## Setup & Development

### Prerequisites
```bash
# PHP 8.1+
# Rust nightly
rustup toolchain install nightly
rustup default nightly

# Docker (optional, for containerized dev)
```

### Build from Source
```bash
git clone git@github.com:<username>/turso-client-php.git
cd turso-client-php
cargo build
# Extension binary in target/debug/liblibsql_php.so (or .dll)
```

### Docker Development
```bash
make compose/up        # x86_64
make compose-arm64/up  # ARM64 (Apple Silicon)
make compose/logs      # View logs
make compose/down      # Stop
```

### Running Tests
```bash
composer install
composer test                    # All tests
composer test:feature            # Feature tests only
vendor/bin/pest --group=BasicCrudTest  # Specific group
```

### Test Structure
- **Unit Tests**: Connection-specific tests (`LocalConnectionTest`, `RemoteConnectionTest`, etc.)
- **Feature Tests**: CRUD, Transactions, Prepared Statements, Batch, Schema
- **TestCase**: Creates `:memory:` database per test, cleans up `.db` files in `afterEach`

## Adding a New Feature

### Pattern to Follow
1. **Define PHP class/struct** in appropriate module (or new module)
2. **Add to registry** if stateful (add to `lib.rs` lazy_static block)
3. **Create provider/hook** if it involves database operations
4. **Implement `#[php_impl]`** methods returning `Result<T, PhpException>`
5. **Update stubs** in `libsql_php_extension.stubs.php`
6. **Add tests** in `tests/Feature/` or `tests/Unit/`
7. **Add docs** in `docs/`

### Example: Adding a New Method to LibSQL
```rust
// In src/lib.rs, inside #[php_impl] impl LibSQL
pub fn my_new_method(&self, arg: String) -> Result<String, PhpException> {
    // 1. Look up connection
    let conn_registry = CONNECTION_REGISTRY.lock().map_err(...)?;
    let conn = conn_registry.get(&self.conn_id).ok_or_else(...)?;
    
    // 2. Do work via runtime
    let result = runtime().block_on(async {
        conn.some_operation(&arg).await
    });
    
    // 3. Return Result
    match result {
        Ok(val) => Ok(val),
        Err(e) => Err(PhpException::from(format!("{:?}", e))),
    }
}
```

## Debugging

### Error Logs
Errors are logged to temp files via `log_error_to_tmp()`. Check your system's temp directory.

### PHP-Side Debugging
```php
// Enable error display
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Check extension loaded
php -m | grep libsql
php --ri libsql
```

### Rust-Side Debugging
```bash
# Build with debug symbols
cargo build

# Use println! or eprintln! in Rust code
# Check PHP error log or temp files for log_error_to_tmp output
```

### Common Pitfalls

| Pitfall | Cause | Solution |
|---------|-------|----------|
| "Connection not found" | conn_id mismatch or already closed | Check registry, ensure `close()` not called prematurely |
| Mutex deadlock | Nested `.lock()` without proper ordering | Use single lock per operation, avoid nested locks |
| "Mode is not available!" | DSN/config doesn't match any pattern | Verify DSN format or use config array |
| Offline writes not syncing | `is_online()` returning false | Check URL reachability, network connectivity |
| Statement not found | stmt_id expired or `finalize()` called | Don't reuse after `finalize()` |

---

# 🧠 AI Skill: Turso PHP Extension Development

## Skill Name
**`turso-php-ext`** — Native PHP Extension Development for libSQL/Turso Databases

## Skill Description
Expert-level knowledge of building, extending, and debugging the turso-client-php native PHP extension — a Rust-based libSQL client supporting local, remote, replica, and offline-write connection modes.

## When to Use This Skill
- Adding new database features to the PHP extension
- Debugging connection/query issues in turso-client-php
- Building similar Rust-based PHP extensions via ext-php-rs
- Integrating libSQL/Turso databases into PHP applications
- Understanding PHP↔Rust FFI patterns for database drivers

## Core Knowledge

### Architecture
- **Registry pattern**: Global `Mutex<HashMap<String, T>>` for connections, transactions, statements
- **Provider pattern**: Isolated connection mode implementations
- **Async bridge**: Single Tokio runtime with `block_on()` for all async operations
- **Type pipeline**: PHP Zval ↔ Rust structs via `FromZval`/`IntoZval` traits

### Connection Modes
1. **local**: File or in-memory SQLite-compatible
2. **remote**: Cloud HTTP connection
3. **remote_replica**: Local cache with remote sync
4. **offline_write** (Turso): Local-first with auto-sync to cloud
5. **sqld_offline_write**: Local-first with auto-sync to self-hosted sqld

### Key Rust Crates
- `ext-php-rs` (0.15.3): PHP extension FFI
- `libsql` (0.9.19): Database engine
- `tokio` (1.47.1): Async runtime
- `reqwest` (0.12.22): HTTP client (blocking)

## Step-by-Step Playbooks

### Playbook 1: Add a New Connection Mode
```
1. Create src/providers/new_mode.rs
2. Implement create_*_connection() function
3. Add module to src/providers/mod.rs
4. Update get_mode() in src/utils/runtime.rs
5. Add match arm in LibSQL::__construct()
6. Add registry if needed (lazy_static in lib.rs)
7. Update PHP stubs in libsql_php_extension.stubs.php
8. Add tests in tests/Unit/
9. Add docs in docs/
```

### Playbook 2: Add a New Method to LibSQL Class
```
1. Add method to #[php_impl] impl LibSQL in src/lib.rs
2. Signature: pub fn method_name(&self, args) -> Result<T, PhpException>
3. Look up connection from registry using self.conn_id
4. Execute via runtime().block_on(async { ... })
5. Handle errors → PhpException
6. Update stubs file
7. Add test
```

### Playbook 3: Debug a Query Issue
```
1. Check PHP error log for PhpException messages
2. Check temp files for log_error_to_tmp() output
3. Verify connection mode matches expected behavior
4. For offline mode: check is_online(), pending_operations
5. For remote mode: verify auth_token and URL format
6. Test with :memory: database to isolate connection issues
7. Run relevant Pest test: vendor/bin/pest --filter=test_name
```

### Playbook 4: Build & Install Extension
```
1. rustup toolchain install nightly && rustup default nightly
2. cargo build --release
3. Copy target/release/liblibsql_php.so to PHP extensions dir
4. Add extension=libsql_php.so to php.ini
5. php -m | grep libsql to verify
```

## Code Patterns to Reuse

### Registry Lookup Pattern
```rust
let registry = SOME_REGISTRY.lock().map_err(|e| {
    PhpException::default(format!("Mutex lock: {}", e))
})?;
let obj = registry.get(&self.id).ok_or_else(|| {
    PhpException::from("Not found")
})?;
```

### Async Execution Pattern
```rust
let result = runtime().block_on(async {
    conn.some_async_operation(&args).await
});
match result {
    Ok(val) => Ok(val),
    Err(e) => Err(PhpException::from(format!("{:?}", e))),
}
```

### PHP Type Conversion Pattern
```rust
impl<'a> FromZval<'a> for MyType {
    const TYPE: DataType = DataType::Mixed;
    fn from_zval(zval: &'a Zval) -> Option<Self> {
        if let Some(array) = zval.array() {
            // Parse array
            Some(MyType { ... })
        } else {
            None
        }
    }
}
```

## Anti-patterns to Avoid

| Anti-pattern | Why | Better Approach |
|-------------|-----|----------------|
| Holding multiple registry locks simultaneously | Deadlock risk | Lock one registry at a time, release before next |
| Creating Tokio runtime per call | Performance hit | Use global `OnceCell<Runtime>` |
| Returning `unwrap()` in PHP-facing code | PHP fatal crash | Return `PhpException` instead |
| Storing heavy objects in PHP class | Memory management issues | Store only IDs, keep real objects in registries |
| Blocking async in sync context without runtime | Panic | Always use `runtime().block_on()` |

---

# ⚡ Cheat Sheet

## Quick Reference

### Connection Strings
```php
// Local
new LibSQL("file:my.db");
new LibSQL(":memory:");
new LibSQL("my.db");

// Remote
new LibSQL("libsql:dbname=https://my-db.turso.io;authToken=tok_abc");

// Config array (replica)
$config = [
    "url" => "file:local.db",
    "authToken" => "tok_abc",
    "syncUrl" => "https://my-db.turso.io",
    "syncInterval" => 5,
    "read_your_writes" => true,
];
new LibSQL($config);
```

### Basic CRUD
```php
$db = new LibSQL(":memory:");
$db->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
$db->execute("INSERT INTO users (name) VALUES (?)", ["Alice"]);
$result = $db->query("SELECT * FROM users");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
$db->close();
```

### Transactions
```php
$tx = $db->transaction("DEFERRED");
$tx->execute("INSERT INTO ...");
$tx->commit();  // or $tx->rollback();
```

### Prepared Statements
```php
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$result = $stmt->query([1]);
$stmt->reset();  // Reuse
$stmt->finalize();
```

### Offline Writes
```php
$db = new LibSQL($config, offline_writes: true);
$db->execute("INSERT INTO ...");  // Writes locally, queues for sync
$db->sync();                       // Manual sync
$db->checkConnectivity();          // Check if online
$db->getPendingOperationsCount();  // See queue size
$db->isOnline();                   // Cached connectivity status
```

### Fetch Modes
```php
$result->fetchArray(LibSQL::LIBSQL_ASSOC);  // [['name' => 'Alice']]
$result->fetchArray(LibSQL::LIBSQL_NUM);    // [[0 => 1, 1 => 'Alice']]
$result->fetchArray(LibSQL::LIBSQL_BOTH);   // Both (default)
$result->fetchArray(LibSQL::LIBSQL_LAZY);   // LibSQLIterator
$result->fetchSingle(LibSQL::LIBSQL_ASSOC); // First row only
```

### Key Files Map
| File | What It Does |
|------|-------------|
| `src/lib.rs` | Main LibSQL PHP class, registries, constants |
| `src/providers/*.rs` | Connection mode implementations |
| `src/hooks/*.rs` | Database operation handlers |
| `src/utils/runtime.rs` | Tokio runtime, DSN parsing, mode detection |
| `src/utils/query_params.rs` | PHP array → libsql params conversion |
| `src/transaction.rs` | LibSQLTransaction PHP class |
| `src/statement.rs` | LibSQLStatement PHP class |
| `src/result.rs` | LibSQLResult PHP class |
| `src/generator.rs` | LibSQLIterator PHP class |
| `libsql_php_extension.stubs.php` | PHP IDE stubs |
| `tests/TestCase.php` | Base test class with :memory: DB |
| `tests/Pest.php` | Pest configuration + cleanup |

### Constants Reference
| Constant | Value | Use |
|----------|-------|-----|
| `LIBSQL_OPEN_READONLY` | 1 | Open DB read-only |
| `LIBSQL_OPEN_READWRITE` | 2 | Open DB read-write |
| `LIBSQL_OPEN_CREATE` | 4 | Create DB if not exists |
| `LIBSQL_ASSOC` | 1 | Fetch associative array |
| `LIBSQL_NUM` | 2 | Fetch numeric-indexed array |
| `LIBSQL_BOTH` | 3 | Fetch both (default) |
| `LIBSQL_ALL` | 4 | Fetch all results |
| `LIBSQL_LAZY` | 5 | Fetch as iterator |

### Common Commands
```bash
# Build
cargo build
cargo build --release

# Test
composer test
vendor/bin/pest --filter=BasicCrud
vendor/bin/pest --group=Feature

# Docker
make compose/up
make compose-arm64/up

# Verify extension
php -m | grep libsql
php --ri libsql
```
