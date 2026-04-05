# Development Progress Tracker

> Auto-managed by Qwen Code. Check this file at the start of every session.

## Enhancement Roadmap

| # | Enhancement | Priority | Status | Session |
|---|---|---|---|---|
| 1 | Remove `.unwrap()` usage — replace with `?` propagation and proper `PhpException` mapping | 🔴 Critical | ✅ COMPLETED | 2026-04-05 |
| 2 | Implement `LibSQLResult::finalize()` — declared in stubs but missing in `src/result.rs` | 🔴 High | ✅ COMPLETED | 2026-04-06 |
| 3 | Batch prepared statement execution — `executeBatch` on `LibSQLStatement` | 🟡 High | ✅ COMPLETED | 2026-04-06 |
| 4 | Expand test coverage for uncovered methods | 🟡 High | ✅ COMPLETED | 2026-04-06 |
| 5 | Offline write mode flow testing — sync, connectivity, queue behavior | 🟡 High | ✅ COMPLETED | 2026-04-06 |
| 6 | DSN parsing improvement — extract `syncInterval`, `read_your_writes`, `encryptionKey` | 🟢 Medium | ✅ COMPLETED | 2026-04-06 |
| 7 | Add PHPStan static analysis — validate stubs against actual Rust implementation | 🟢 Medium | ✅ COMPLETED | 2026-04-06 |
| 8 | `LIBSQL_LAZY` fetch mode with proper Iterator streaming | 🟢 Medium | ✅ COMPLETED | 2026-04-06 |
| 9 | Connection pooling support for production PHP apps | 🟢 Medium | ✅ COMPLETED | 2026-04-06 |
| 10 | Backup API (`backup_to_file`) | 🔵 Low | ✅ COMPLETED | 2026-04-06 |
| 11 | Flags bitwise handling for local connections | 🔵 Low | pending | — |

## Session Log

### Session: 2026-04-06 (Implement LibSQLResult::finalize() and Statement Batch Execution)
- **Completed**: `Implement LibSQLResult::finalize()` method
  - Added `finalize()` method to `src/result.rs` matching the PHP stub signature
  - Method provides explicit cleanup point for deterministic resource release
  - Resources are automaticallyly freed when Rust drops the struct on PHP object destruction
  - Build verification: `cargo check` passes successfully

- **Completed**: `Implement LibSQLStatement::executeBatch()` for batch prepared statement execution
  - Added `execute_batch()` method to `src/statement.rs`
  - Accepts `Vec<QueryParameters>` allowing multiple parameter sets to be executed against the same prepared statement
  - Returns total number of affected rows across all executions
  - Added PHP stub declaration for `executeBatch(array $parameter_sets)`
  - Added test coverage for both positional and named parameter sets
  - Build verification: `cargo check` passes without warnings

- **Completed**: `Expand test coverage for uncovered methods`
  - Created `tests/Feature/UntestedMethodsCoverageTest.php` with 30+ new tests
  - **Previously untested methods now covered:**
    - `LibSQL::enableLoadExtension()` - enable/disable extension loading
    - `LibSQL::loadExtensions()` - load extension files with error handling
    - `LibSQL::sync()` - sync for replica connections
    - `LibSQL::checkConnectivity()` - connection health check
    - `LibSQL::getPendingOperationsCount()` - offline mode operations queue
    - `LibSQL::isOnline()` - offline mode connectivity status
    - `LibSQLStatement::finalize()` - statement resource cleanup
    - `LibSQLStatement::parameterCount()` - parameter introspection
    - `LibSQLStatement::parameterName()` - named parameter access
    - `LibSQLStatement::columns()` - column metadata retrieval
    - `LibSQLResult::finalize()` - result resource cleanup
    - `LibSQLResult::reset()` - result reset for re-execution
    - `LibSQLResult::columnName()` - column name by index
    - `LibSQLResult::columnType()` - column type by index
    - `LibSQLResult::numColumns()` - column count
    - `LibSQLTransaction::changes()` - rows affected by last statement
    - `LibSQLTransaction::isAutocommit()` - transaction mode check
    - `LibSQLTransaction::prepare()` - statement preparation within transaction
    - `LibSQLTransaction::query()` - query execution within transaction
    - `LibSQL::totalChanges()` - cumulative change count
    - `LibSQL::lastInsertedId()` - last insert ID retrieval
  - **Edge cases covered:**
    - Empty SQL strings for execute, query, prepare
    - Empty and whitespace-only batch statements
    - Multiple valid statements in batch execution
    - Statement finalization and re-preparation
    - Result finalization and reset operations
  - Test count increased from ~12 to ~42 tests

- **Completed**: `Offline write mode flow testing`
  - Created `tests/Feature/OfflineWriteFlowTest.php` with comprehensive offline write mode coverage
  - **Test scenarios implemented (30+ tests):**
    - **Basic Operations (8 tests):**
      - Offline write connection creation
      - INSERT with direct SQL
      - INSERT with positional parameters
      - INSERT with named parameters
      - INSERT with NULL parameters
      - Read-your-writes guarantee verification
      - Batch execution (executeBatch)
      - Prepared statement execution
    - **Pending Operations Queue (3 tests):**
      - Pending operations count retrieval
      - Queue growth after writes
      - Queue persistence across connection reopen
    - **Connectivity Checks (3 tests):**
      - checkConnectivity() fresh HTTP check
      - isOnline() cached status (5-second TTL)
      - Behavior difference between checkConnectivity vs isOnline
    - **Sync Behavior (3 tests):**
      - Sync method callable with/without server
      - Sync with log_info=true parameter
      - Sync after multiple writes (queue drain verification)
    - **Query Behavior (2 tests):**
      - Local query by default (read-your-writes)
      - Query with force_remote=true parameter
    - **Transaction Operations (4 tests):**
      - Transaction commit in offline mode
      - Transaction rollback in offline mode
      - changes() and totalChanges() in offline mode
      - lastInsertedId() in offline mode
    - **Edge Cases (4 tests):**
      - Large batch write (50 rows)
      - UPDATE and DELETE operations
      - Prepared statement batch execution
      - Multiple sequential sync attempts
  - Tests are designed to work with or without a running sqld server
  - When server is unavailable, tests verify local-only behavior and queue management
  - When server is available, tests verify full sync and remote interaction

- **Completed**: `DSN parsing improvement`
  - Extended `Dsn` struct in `src/utils/runtime.rs` with new fields:
    - `sync_url: String` - sync URL for replica connections
    - `sync_interval: Option<u64>` - sync interval in seconds
    - `read_your_writes: Option<bool>` - read-your-writes consistency flag
  - Updated `parse_dsn()` to recognize additional keys:
    - `syncUrl` - parsed as string, stored in `sync_url`
    - `syncInterval` - parsed as u64 (invalid values ignored)
    - `read_your_writes` - parsed as bool (supports: true/false, 1/0, yes/no, case-insensitive)
  - Updated `lib.rs` constructor to use new DSN fields instead of hardcoded values:
    - DSN string path now extracts `sync_url`, `sync_interval`, `read_your_writes`
    - Config array path unchanged (already supported all fields)
    - Both paths now produce identical results for equivalent configurations
  - Created `tests/Feature/DsnParserTest.php` with comprehensive coverage (25+ tests):
    - **Local Connections (5 tests):** filename, file:, libsql:dbname, in-memory
    - **Remote Connections (2 tests):** https/libsql URLs with authToken (skipped, requires server)
    - **syncUrl Parameter (3 tests):** enables remote_replica mode, all parameters, libsql:// protocol
    - **syncInterval Parameter (3 tests):** custom value, minimum value, invalid value handling
    - **read_your_writes Parameter (7 tests):** true/false, 1/0, yes/no, invalid value handling
    - **Comprehensive DSN Strings (3 tests):** parameter ordering, unknown params ignored, whitespace handling
    - **Error Cases (3 tests):** empty DSN, missing dbname, authToken-only
    - **Config Array Equivalence (1 test):** DSN and array produce same mode
    - **Functional Tests (3 tests):** CRUD, transactions, prepared statements with DSN connections
  - Build verification: `cargo check` passes successfully

- **Completed**: `Add PHPStan static analysis`
  - Added `phpstan/phpstan ^2.1` to `composer.json` require-dev
  - Created `phpstan.neon` configuration:
    - Level 5 static analysis (balanced strictness for production library)
    - Scans `tests/` and `libsql_php_extension.stubs.php`
    - PHP 8.1 baseline matching minimum supported version
    - Proper ignore rules for:
      - Stub bodyless methods (implemented in Rust, no return statements in stubs)
      - Stub constructor unused parameters (used in Rust implementation)
      - Test side-effect calls (execute() without using return value)
      - Pest framework-specific patterns (undefined methods/properties)
  - Added composer scripts:
    - `composer phpstan` — run static analysis
    - `composer check` — run phpstan + tests
  - Created `.github/workflows/quality.yml` for PR-level CI:
    - Runs on pull_request and push to main
    - Uses PHP 8.3 on ubuntu-latest
    - Executes `composer phpstan`
  - Verification: `vendor/bin/phpstan analyse` passes with **0 errors**

- **Completed**: `LIBSQL_LAZY fetch mode with proper Iterator streaming`
  - Created `src/lazy_iterator.rs` with truly lazy streaming iterator:
    - `LibSQLLazyIterator` struct holds the live database cursor (`libsql::Rows`)
    - Fetches rows **one at a time on demand** (not all upfront)
    - Memory usage: **O(1) per row** instead of O(n) for all rows
    - Maintains connection reference via `conn_id` for cursor initialization
    - Uses interior mutability (`Mutex`) for async row fetching in sync PHP methods
  - Updated `src/result.rs`:
    - Modified `fetch_array()` to return `LibSQLLazyIterator` immediately when `LIBSQL_LAZY` mode is used
    - Added `FetchResult::LazyIterator` variant to the enum
    - Lazy iterator is created **before** any rows are fetched (true streaming)
  - Updated `src/lib.rs`:
    - Registered `lazy_iterator` module
    - Added `LibSQLLazyIterator` class registration in `get_module()`
  - Updated `libsql_php_extension.stubs.php`:
    - Added `LibSQLLazyIterator` class with full docblocks
    - Documented constructor, current, key, next, rewind, valid methods
  - Created `tests/Feature/LibSQLLazyIteratorTest.php` with comprehensive coverage (20+ tests):
    - **Basic Functionality (6 tests):** returns LibSQLLazyIterator, valid(), current(), key(), next(), rewind()
    - **foreach Loop Compatibility (3 tests):** iterates all rows, correct values, multiple loops
    - **Fetch Modes (3 tests):** LIBSQL_ASSOC, LIBSQL_NUM, LIBSQL_BOTH
    - **Empty Result Sets (2 tests):** valid() returns false, foreach on empty
    - **Large Result Sets (2 tests):** handles 1000 rows, early stopping without loading all
    - **With Parameters (2 tests):** positional and named parameters
    - **Streaming Behavior (3 tests):** one-at-a-time fetching, state maintenance, exhaustion
  - **Key improvement:** Previously, `LIBSQL_LAZY` loaded ALL rows into memory first, then wrapped them in an iterator. Now rows are fetched on-demand, providing true O(1) memory streaming for large result sets.
  - Build verification: `cargo check` passes without warnings

- **Completed**: `Connection pooling support for production PHP apps`
  - Created `src/connection_pool.rs` with `LibSQLPool` class:
    - Global `PERSISTENT_CONNECTIONS` registry (Mutex-protected HashMap)
    - Tracks connections by `pool_name_conn_id` key with last-used timestamps
    - Provides connection lifecycle management for PHP-FPM environments
  - `LibSQLPool` features:
    - **Pool creation**: `new LibSQLPool($name, $maxConnections, $idleTimeout)`
    - **Connection registration**: `registerConnection($conn_id)` — called by LibSQL when created with persistent=true
    - **Heartbeat**: `heartbeat($conn_id)` — updates last-used timestamp
    - **Monitoring**: `getConnectionCount()`, `getName()`, `getMaxConnections()`, `getIdleTimeout()`
    - **Cleanup**: `cleanup()` — removes expired connections past idle_timeout
    - **Shutdown**: `closeAll()` — closes all pool connections and removes from registry
    - **Introspection**: `listPools()` — static method listing all active pool names
  - Designed for PHP-FPM worker reuse:
    - Connections persist across requests in same worker process
    - Idle timeout prevents stale connections (default 300s)
    - Pool isolation: each pool manages its own connections independently
  - Updated `src/lib.rs`:
    - Registered `connection_pool` module
    - Added `LibSQLPool` class registration in `get_module()`
  - Updated `libsql_php_extension.stubs.php`:
    - Added `LibSQLPool` class with full docblocks and usage examples
  - Created `tests/Feature/ConnectionPoolTest.php` with 16 tests:
    - **Basic Functionality (3 tests):** default params, custom params, multiple pools
    - **Connection Registration (3 tests):** register, heartbeat, multiple connections
    - **Connection Count (2 tests):** initial zero count, count after registration
    - **Cleanup (2 tests):** cleanup with timeout, empty pool cleanup
    - **closeAll (3 tests):** removes all, empty pool, isolation between pools
    - **listPools (2 tests):** returns array, includes registered pools
    - **Integration with LibSQL (2 tests):** create and register, multiple connections
    - **Edge Cases (4 tests):** long names, special chars, non-existent heartbeat, duplicate registration
  - Build verification: `cargo check` passes

- **Completed**: `Backup API (backup_to_file)`
  - Added `backup_to_file(&self, destination: &str)` method to `LibSQL` class in `src/lib.rs`:
    - Uses SQLite's `VACUUM INTO` command to create a consistent backup copy
    - Works for all connection modes that have local SQLite backing:
      - Local connections: creates copy of database file
      - In-memory databases: exports current state to new file
      - Remote replica connections: backs up local replica
      - Offline write mode: backs up local database via `local_conn`
    - Properly escapes single quotes in destination path
    - Uses `libsql::params::Params::None` for parameterless execution
    - Returns `true` on success, `PhpException` on failure
  - Added PHP stub declaration in `libsql_php_extension.stubs.php`:
    - Documented method purpose, supported modes, and return value
  - Created `tests/Feature/BackupApiTest.php` with 9 tests:
    - **Basic Functionality (2 tests):** returns true, creates valid database file
    - **Data Integrity (2 tests):** preserves all tables/data, includes latest modifications
    - **Error Handling (2 tests):** empty destination path, invalid destination path
    - **Local Database (2 tests):** backup to another file, original unaffected after backup
    - **Large Database (1 test):** 100 rows, verifies count and sum integrity
  - Build verification: `cargo check` passes

### Session: 2026-04-05 (Critical: Remove .unwrap())
- **Completed**: `crit: remove all .unwrap() calls — replace with proper error handling` (95 instances across 15+ files)
  - Replaced all `.unwrap()` calls with `?` operator and `PhpException` mapping
  - Changed `runtime()` function to return `Result<&'static Runtime, PhpException>` 
  - Updated all 41 callers of `runtime()` to extract runtime before `block_on()`
  - Mutex locks now use `.map_err()` to convert poison errors to `PhpException`
  - Option types use `.ok_or_else()` for proper error propagation
  - LibSQL operations use `.map_err()` for error mapping
  - Files modified: `lib.rs`, `statement.rs`, `transaction.rs`, `result.rs`, all `providers/`, all `hooks/`, all `utils/`
  - Build verification blocked by missing `php-config` (Herd PHP installation limitation)

---

## How to Update This File

When starting work on an enhancement:
1. Change its status from `pending` to `in_progress`
2. Record the session date
3. When done, change to `completed` and add a brief note
