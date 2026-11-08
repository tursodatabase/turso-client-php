# Constants and Fetch Modes

All constants live on the `LibSQL` class. This page is the single source
for their values and for the shape each fetch mode returns; the method
reference lives in
[`LibSQLResult Class`](LibSQLResult-class.md).

---

## Open flags

| Constant | Value | Meaning |
| --- | --- | --- |
| `LibSQL::OPEN_READONLY` | 1 | open an existing file for reading only |
| `LibSQL::OPEN_READWRITE` | 2 | open an existing file for reading and writing |
| `LibSQL::OPEN_CREATE` | 4 | create the file if it does not exist |

The constructor's default `$flags` is `6` — `OPEN_READWRITE | OPEN_CREATE`
— so plain `new LibSQL("file:fresh.db")` creates the file:

```php
$db = new LibSQL("file:fresh.db");                      // created (default 6)
$db = new LibSQL("file:x.db", flags: LibSQL::OPEN_READONLY | LibSQL::OPEN_CREATE); // read+create
```

Two verified behaviors around flags:

* `OPEN_READONLY` **without** `OPEN_CREATE` fails for a file that does not
  exist — SQLite error code 14 (`SQLITE_CANTOPEN`):
  `Connection failed: Failed to connect to database: ... Unable to open
  connection to local database file:...: 14`
* When using named arguments, every parameter between `$config` and the
  first named one you pass must be supplied explicitly; the constructor
  rejects skipped optional slots (see
  [Error Semantics](error-semantics.md)).

## Fetch modes

`fetchArray($mode)` / `fetchSingle($mode)` decide the returned shape. The
default mode is **`LIBSQL_BOTH`**. Given a table `users(id, name)` with one
row `(1, 'Ada')` and the query `SELECT id, name FROM users`:

| Constant | Value | Shape returned by `fetchArray` |
| --- | --- | --- |
| `LIBSQL_ASSOC` | 1 | `[["id" => 1, "name" => "Ada"]]` |
| `LIBSQL_NUM` | 2 | `[[1, "Ada"]]` |
| `LIBSQL_BOTH` | 3 (default) | `[["id" => 1, "0" => 1, "name" => "Ada", "1" => "Ada"]]` |
| `LIBSQL_ALL` | 4 | full structure — `["columns" => [...], "rows" => [...], "rows_affected" => 0, "last_insert_rowid" => 0]` |
| `LIBSQL_LAZY` | 5 | streaming `LibSQLIterator` (see below) |

`fetchSingle()` returns the **first row only** in the same shape (an empty
array `[]` when nothing matches), and for `LIBSQL_ALL` it returns one full
structure with a single `rows` entry.

### Column order is the query's order

Row key/numeric order follows the **query text**, not the table schema:

```php
$db->query("SELECT name, id FROM users")->fetchArray(LibSQL::LIBSQL_NUM)[0];
// ["Ada", 1] — name first, exactly as written in the SELECT

array_keys($db->query("SELECT name, id FROM users")->fetchArray()[0]);
// ["name", "id"]
```

---

## LIBSQL_LAZY — streaming iteration

```php
$it = $db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_LAZY);

do {
    $row = $it->current(); // ["id" => 1, "0" => 1, "name" => "Ada", "1" => "Ada"]
    // ... process $row
    $it->next();
} while ($it->valid());
```

Verified properties of row-set iterators:

* Rows stream one at a time (constant memory); nothing is materialized up
  front — not even the first row before your first `current()`.
* Each round's `key()` is the 0-based row index — with the correct pattern
  above, three rows yield keys `0, 1, 2`.
* `current()` triggers the (lazy) row fetch; `key()` does **not**. A loop
  that reads only `key()` while never calling `current()`/`valid()` first
  can read a past-the-end counter, so always let `current()` (or
  `valid()`) lead each round.
* After the last row: `current() === null` and `valid() === false`.
  Calling `rewind()` does **not** restart — the row set cursor is
  forward-only. Run the query again for a second pass.
* **Zero-row results produce exactly one round with `current() === null`**
  — guard with `if ($it->current() !== null)` (or use `fetchSingle()` and
  its empty-array check) if you can't handle that one empty pass.
* Errors hitting the stream surface as PHP exceptions rather than
  truncating the iteration silently.

### `fetchSingle(LibSQL::LIBSQL_LAZY)` — one row, values stream

The single-row `LAZY` call wraps the first row's **column values** into an
iterator (numerically keyed `0..`):

```php
$it = $db->query("SELECT id, name FROM users WHERE id = 1")->fetchSingle(LibSQL::LIBSQL_LAZY);
$vals = [];
do { $vals[] = $it->current(); $it->next(); } while ($it->valid());
// [1, "Ada"]
```

This iterator is **array-backed**, so `rewind()` here *does* restart —
unlike the row-set iterator above. On zero rows you get an iterator whose
first `current()` is `null`.

### `LibSQLIterator` is public

`LibSQLIterator` can be constructed from any PHP array — handy for the
same iteration protocol over in-memory data:

```php
$a = new LibSQLIterator([5, 6, 7]);
$vals = [];
do { $vals[] = $a->current(); $a->next(); } while ($a->valid());
// [5, 6, 7]
$a->rewind(); // array-backed: restarts
```

Array-backed iterators restart on `rewind()`; row-set iterators do not.

---

## Related

* [`LibSQLResult Class`](LibSQLResult-class.md) — every fetch method in detail
* [`LibSQLStatement Class`](LibSQLStatement-class.md#typed-bindings) — how PHP types bind to SQLite storage classes
* [Error Semantics](error-semantics.md) — what gets thrown when values or modes are wrong
