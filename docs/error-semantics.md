# Error Semantics

Since the panic-free error-mapping work, the extension no longer unwinds or
crashes the PHP process. Every failure that the engine reports is mapped to
a **PHP `Exception`**, and the failure surfaces at a predictable point in
the call sequence. This page is the single reference for what throws, when
it throws, and the exact messages to expect.

For the full per-method tables see
[LibSQLStatement Class](LibSQLStatement-class.md#error-semantics),
[LibSQLTransaction Class](LibSQLTransaction-class.md#error-semantics),
[LibSQLResult Class](LibSQLResult-class.md#error-semantics) and the
[offline flavor matrix](offline-writes-flavors.md#error-text-reference).

---

## Two error layers

The extension produces errors from two different layers, and only one of
them is an `Exception`:

| Layer | Thrown class | Example |
| --- | --- | --- |
| Engine (runtime failure) | `Exception` | `Connection not found`, `Execute returned rows` |
| PHP argument shape | `ArgumentCountError` (an `Error`, **not** an `Exception`) | skipping optional named-argument slots |

This distinction matters for catch blocks: `catch (Exception $e)` will not
see argument-shape errors. Use `catch (Throwable $e)` when you want both:

```php
try {
    $db = new LibSQL($config);
    $db->sync();
} catch (Exception $e) {
    // engine failure: sync failed, connection refused, ...
    error_log("sync problem: " . $e->getMessage());
}
```

The named-argument rule: extension functions cannot fill in skipped
optional parameter slots, so every parameter **before** the first named one
you pass must be given explicitly:

```php
new LibSQL(config: $config, flags: LibSQL::OPEN_READWRITE);
// ArgumentCountError: LibSql::__construct(): Argument #2 ($sqld_offline_mode)
// must be passed explicitly, because the default value is not known
```

---

## When errors surface

Error *timing* differs between the query paths — fetches re-run the query,
so most fetch-time failures belong there:

| Call | Behavior on bad input |
| --- | --- |
| `prepare()` | throws immediately: `Failed to prepare statement: SQLite failure: ...` |
| `$stmt->execute()` | throws when the statement is not DML (`Execute returned rows`) or the SQL fails at run time |
| `$db->execute()`, `$trx->execute()` | throws immediately: `Execution error: ...` |
| `$db->executeBatch()` | the whole statement batch runs; the first failing statement throws a bare `SQLite failure: ...` |
| `$db->query()` | **does not throw at call time** — returns a result object; failures surface when you fetch |
| `fetchArray()` / `fetchSingle()` | throws the deferred failure: bare `SQLite failure: ...` |
| `$trx->query()` | throws immediately: `Query failed: SQLite failure: ...` |

This is observable behavior worth designing around:

* `query("SELECT * FROM missing_table")` returns before any SQLite work
  reports failure; the error is reported at fetch:
  `SQLite failure: `no such table: no_table``
* Because each fetch re-executes the statement, a result object is a
  live view: fetching twice returns the data twice, and new rows written
  between fetches appear in later fetches.

---

## Message reference

Exact strings, each verified against the extension:

| Message | Where / when |
| --- | --- |
| `Invalid configuration: expected a DSN string or a config array` | `new LibSQL()` first argument is neither a DSN nor an array |
| `Connection failed: Failed to connect to database: ...` | opening fails (missing file without `OPEN_CREATE`, offline replica, dead port, ...) |
| `Replica database build failed: ...` | embedded replica initial sync fails at construction |
| `Offline-write connection failed: ...` | Turso Cloud offline flare fails at construction |
| `Connection not found` | statement/transaction construction with a stale connection; any direct call after `close()` (e.g. `query()`) |
| `Connection ID not found.` | calling `close()` a second time |
| `Failed to prepare statement: SQLite failure: ...` | invalid SQL / missing table at `prepare()` |
| `Execute returned rows` | `$stmt->execute()` on a statement that returned row data (DML-only rule) |
| `Execution error: Execute returned rows` | `$db->execute()` / `$trx->execute()` on a row-returning SELECT |
| `Execution error: SQLite failure: ...` | `$db->execute()` / `$trx->execute()` on failing SQL |
| `SQLite failure: ...` | `$db->executeBatch()` on a failing batch; deferred fetch errors from `$db->query()` |
| `Query failed: SQLite failure: ...` | `$trx->query()` on failing SQL (immediate) |
| `Failed to begin transaction: ...` | `$db->transaction()` when SQLite refuses `BEGIN` (lock contention) |
| `Statement not found` | using a statement after `finalize()` (or after GC cleanup) |
| `Transaction not found` | second `commit()` / `rollback()` on a finished transaction |
| `Column index N out of bounds` | `columnName()` out of range (note: `columnType()` does **not** throw — it returns `"Null"`) |

Offline-write mode additions — see the
[flavor matrix](offline-writes-flavors.md) for the complete list:

* `prepare() is not supported in offline_write mode: ...` and
  `transaction() is not supported in offline_write mode: ...` (journaled
  flavor; both explain the journal-bypass rationale)
* `Connectivity check only available in offline_write mode`,
  `Online status check only available in offline_write mode`,
  `Pending operations only available in offline_write mode`
* `local mode does not support sync`
* `Sync failed: Cannot sync: no internet connection` (journaled flavor,
  offline at sync time; pending operations are preserved)

Also not error condition — deliberate non-throwing paths:

* `fetchSingle()` with zero matching rows returns `[]`, not an exception.
* `columnType()` out of range returns `"Null"` (only `columnName()` throws).
* `captureIt()` with an **empty** `cdc_url` returns `false` silently; only
  a `null` `cdc_url` throws `CDC URL is not configured`.
* The `LibSQL::LIBSQL_ALL` fetch of a zero-row SELECT is a structure with
  empty rows — no throw (see
  [fetch modes](constants.md#fetch-modes)).

---

## Diagnostics

Engine-side failures are additionally written (prefixed with a Unix
timestamp) to a temporary file — **opt-in**, because writing SQL and
parameter content to shared tmp is not safe by default. Set
`LIBSQL_PHP_ERROR_LOG` to a file path to enable it:

```bash
export LIBSQL_PHP_ERROR_LOG=/var/log/php/libsql.log
```

See [Configuration](configuration.md#libsql_php_error_log-off-by-default)
for the full description.
