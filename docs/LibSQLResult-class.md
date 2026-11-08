# LibSQLResult Class

`LibSQLResult` represents the **result of a query**. You obtain one from
`$db->query(...)`, `$stmt->query(...)` or `$trx->query(...)` — the class
constructor requires an internal connection ID that is not exposed as a
public property, so treat it as an engine-managed object rather than
something you build yourself.

Two facts worth knowing up front:

* **Column metadata is available without fetching rows** — `numColumns()`,
  `columnName()` (and `columnType()` for the declared type) work on
  zero-row results.
* **Value types are per-row in SQLite**, so `columnType()` reports the type
  of the first row when one exists, and the row set's declared type
  otherwise.

---

## Class Synopsis

```php
class LibSQLResult
{
    public function __construct(string $config, string $sql, array $parameters = []);
    public function fetchArray(int $mode = 3): array|LibSQLIterator;
    public function fetchSingle(int $mode = 3): array|LibSQLIterator;
    public function finalize(): void;          // see note below
    public function reset(): void;
    public function columnName(int $column): string;
    public function columnType(int $column): string;
    public function numColumns(): int;
}
```

> `finalize()` is declared in `libsql_php_extension.stubs.php` but not
> implemented by the engine yet — calling it fails with
> `Call to undefined method`. Resources are released automatically on GC;
> use `reset()` to re-execute instead.

---

## Fetch modes

The fetch mode (the `LibSQL::LIBSQL_*` constants) decides the shape of the
returned data. Given a table with columns `id` and `name` and one row
`(1, 'Ada')`:

| Constant | Value | Shape |
| --- | --- | --- |
| `LIBSQL_ASSOC` | 1 | list of string-keyed rows |
| `LIBSQL_NUM` | 2 | list of numerically-keyed rows, in query column order |
| `LIBSQL_BOTH` | 3 | both — name keys **plus** numeric keys (default) |
| `LIBSQL_ALL` | 4 | full result-set structure (`columns`/`rows`/...) |
| `LIBSQL_LAZY` | 5 | streaming [`LibSQLIterator`](#streaming-with-libsqliterator) |

### `fetchArray(int $mode = 3): array|LibSQLIterator`

Fetches the **entire result set** at once:

```php
$db = new LibSQL(":memory:");
$db->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
$db->execute("INSERT INTO users VALUES (1, 'Ada')");

$db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_ASSOC);
// [["id" => 1, "name" => "Ada"]]

$db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_NUM);
// [[1, "Ada"]] — column order always follows the query, never
// internal iteration order

$db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_BOTH);
// ["id" => 1, "0" => 1, "name" => "Ada", "1" => "Ada"]

$db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_ALL);
// [
//   "columns"           => ["id", "name"],
//   "rows"              => [["id" => 1, "name" => "Ada"]],
//   "rows_affected"     => 0,
//   "last_insert_rowid" => 0,
// ]

$db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_LAZY);
// LibSQLIterator — see "Streaming with LibSQLIterator"
```

### `fetchSingle(int $mode = 3): array|LibSQLIterator`

Fetches the **first row**; an empty array when the query matches no rows
(not `false`, not `null`):

```php
$db->query("SELECT id, name FROM users WHERE id = 1")->fetchSingle(LibSQL::LIBSQL_ASSOC);
// ["id" => 1, "name" => "Ada"]

$db->query("SELECT id FROM users WHERE id = 999")->fetchSingle(LibSQL::LIBSQL_ASSOC);
// []
```

With `LIBSQL_LAZY`, `fetchSingle()` wraps the one row into a
`LibSQLIterator` over that row's column values (numerically keyed, `0..`):

```php
$it = $db->query("SELECT id, name FROM users WHERE id = 1")->fetchSingle(LibSQL::LIBSQL_LAZY);
$vals = [];
do { $vals[] = $it->current(); $it->next(); } while ($it->valid());
// [1, "Ada"]
```

### `numColumns(): int` and `columnName(int $column): string`

Flow from the result set's column metadata — no row fetch required, so
zero-row queries are fine:

```php
$db->query("SELECT id, name FROM users WHERE id = 999")->numColumns();  // 2
$db->query("SELECT id, name FROM users WHERE id = 999")->columnName(1); // "name"
```

An out-of-range index throws: `Column index 5 out of bounds`.

### `columnType(int $column): string`

SQLite column types are **per-row**. When a row exists, the first row's
value type is reported (`"Integer"`, `"Text"`, `"Float"`, `"Blob"` or
`"Null"`). When the result is empty, the row set's declared column type is
reported instead — locally that is `"Null"`, while remote/Hrana queries
deliver the server's declared type:

```php
$db->query("SELECT id FROM users")->columnType(0);                 // "Integer"
$db->query("SELECT id FROM users WHERE id = 999")->columnType(0); // "Null"
```

### `reset(): void`

Resets the underlying statement(s) on the connection so the result can be
re-executed. Note this is per-connection: other active statements on the
same connection are reset too.

---

## Streaming with LibSQLIterator

`fetchArray(LibSQL::LIBSQL_LAZY)` returns a `LibSQLIterator` that pulls rows
one at a time instead of materializing the whole result set — constant
memory for large scans. The documented pattern is
**`current()` → `next()` → `valid()`**:

```php
$it = $db->query("SELECT id, name FROM users")->fetchArray(LibSQL::LIBSQL_LAZY);

do {
    $row = $it->current(); // ["id" => ..., "0" => ..., "name" => ..., "1" => ...]
    // ... use $row
    $it->next();
} while ($it->valid());

// after the data ends: $it->current() === null and $it->valid() === false
```

Notes:

* The iterator is **forward-only**: `rewind()` is a no-op, because the row
  set cannot be replayed. Run the query again to iterate a second time.
* Iterated rows come in `LIBSQL_BOTH` shape (name keys plus numeric keys).
* Row-level errors surface as PHP exceptions instead of silently cropping
  the iteration short.

---

## Error semantics

| Situation | Result |
| --- | --- |
| Out-of-range column on `columnName()` | Throws `Column index N out of bounds` |
| Out-of-range column on `columnType()` | Does not throw; returns `"Null"` |
| `finalize()` | Throws `Call to undefined method` (not implemented) |
| `fetchSingle()` with no matching row | `[]` (empty array) |
