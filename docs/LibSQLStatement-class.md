# LibSQLStatement Class

`LibSQLStatement` represents a **prepared statement** compiled by the
engine. You create one through `$db->prepare($sql)` — the class constructor
requires an internal connection ID that is not exposed as a public
property, so always go through that path.

Prepared statements are the right tool when the same SQL runs repeatedly
with different parameters (cheaper than re-parsing) or when you need SQL
metadata (parameter count/names, column info) before executing. The native
resources are released automatically when the PHP object is garbage
collected, so explicit `finalize()` is optional.

---

## Class Synopsis

```php
class LibSQLStatement
{
    public function __construct(string $conn_id, string $sql);
    public function finalize(): void;
    public function bindNamed(array $parameters): void;
    public function bindPositional(array $parameters): void;
    public function execute(array $parameters = []): int;
    public function query(array $parameters = []): LibSQLResult;
    public function reset(): void;
    public function parameterCount(): int;
    public function parameterName(int $idx): ?string;
    public function columns(): array;
}
```

---

### `__construct(string $conn_id, string $sql)`

Not meant to be called directly — use `$db->prepare()`:

```php
$db = new LibSQL(":memory:");
$stmt = $db->prepare("INSERT INTO users (name, age) VALUES (?, ?)");
```

Construction failures throw: unknown connection ID (`Connection not
found`), missing table, invalid SQL (`Failed to prepare statement: ...`).

### `bindNamed(array $parameters): void`

Binds named parameters. Keys are matched with or without the `:`, `@`, `$`,
`?` prefix:

```php
$stmt = $db->prepare("INSERT INTO users (name, age) VALUES (:name, :age)");
$stmt->bindNamed(['name' => 'Ada', 'age' => 36]);
$stmt->execute(); // 1 — the stored bindings were used
```

Values keep their PHP types and bind with them (see
[typed bindings](#typed-bindings)).

### `bindPositional(array $parameters): void`

Binds positional parameters (1-based). The placeholder style is detected
automatically from the prepared SQL: `$1` (dollar), `?` (question mark —
bound as `?1`, `?2`, ...), or `@1`:

```php
$stmt = $db->prepare("SELECT * FROM users WHERE age > ? AND name <> ?");
$stmt->bindPositional([30, 'Bob']);
$rows = $stmt->query()->fetchArray(LibSQL::LIBSQL_ASSOC);
```

Positional binding order is **deterministic**: parameters are ordered by
placeholder index (`?1`, `?2`, ...) rather than internal hash-map iteration
order.

### `execute(array $parameters = []): int`

Runs DML and returns the number of affected rows:

```php
$stmt = $db->prepare("INSERT INTO users (name) VALUES (?)");
$stmt->execute(['Grace']);
// 1
```

Behavior notes:

* `execute()` is for DML only — a statement that produces rows is rejected
  with `Execute returned rows`. Use `query()` for anything that returns
  data.
* Called without arguments, it uses the stored bindings from
  `bindNamed()`/`bindPositional()`. Re-binding in a loop works: every
  `execute()` resets the statement first (_SQLite retains previous bindings
  across the implicit reset, and re-binding without a reset used to be
  silently ignored_):

```php
$stmt = $db->prepare("INSERT INTO users (name) VALUES (?)");
foreach (['u1', 'u2', 'u3'] as $u) {
    $stmt->bindPositional([$u]);
    $stmt->execute();
}
// table: [u1, u2, u3] — not [u1, u1, u1]
```

* Arguments passed directly to `execute(...)` **replace** the stored
  bindings for that call; the next `execute()` uses whatever was bound
  again.

### `query(array $parameters = []): LibSQLResult`

Runs the statement as a query and returns a
[`LibSQLResult`](LibSQLResult-class.md):

```php
$stmt = $db->prepare("SELECT id, name FROM users WHERE age = ?");
$result = $stmt->query([36]);
$row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);
// ["id" => 1, "name" => "Ada"]
```

### `reset(): void`

Resets the statement to its initial state — **stored bindings are cleared
too**. Useful for breaking a rebinding cycle without constructing a new
statement object:

```php
$stmt->reset();
$stmt->bindPositional([42]); // re-bind after reset
$stmt->execute();
```

### `parameterCount(): int`

Number of parameters declared by the statement:

```php
$stmt = $db->prepare("SELECT :a, :b");
$stmt->parameterCount(); // 2
```

### `parameterName(int $idx): ?string`

The parameter name at a **1-based** index, without its `:`, `@`, `$`, `?`
prefix. Index `0` (or out of range) returns `null` instead of throwing:

```php
// SELECT :limit → parameterName(1) === "limit"
// SELECT ?1, ?2 → parameterName(1) === "1", parameterName(2) === "2"
```

### `columns(): array`

Column metadata the statement would produce — each entry carries `name`,
`origin_name`, `table_name`, `database_name`, and (when available)
`decl_type`:

```php
$stmt = $db->prepare("SELECT id, name FROM users");
$stmt->columns();
// [
//   ['name' => 'id', 'origin_name' => 'id', 'table_name' => 'users',
//    'database_name' => 'main', 'decl_type' => 'INTEGER'],
//   ['name' => 'name', ...],
// ]
```

### `finalize(): void`

Removes the statement from the registry and releases its native resources.
After calling it, any further use throws `Statement not found` (the same
exception the destructor-driven cleanup would hide). Because the Drop
implementation finalizes automatically at GC time, `unset($stmt)` alone is
usually sufficient.

---

## Typed bindings

Bound values keep their PHP types and are stored as the matching SQLite
storage class — parameters are no longer coerced to TEXT:

| PHP value | SQLite type |
| --- | --- |
| `int` | INTEGER |
| `float` | REAL |
| `string` | TEXT |
| `bool` | INTEGER (1/0) |
| `null` | NULL |
| array of ints | BLOB (`[1, 2, 255]` → bytes `01 02 FF`) |

---

## Error semantics

| Situation | Result |
| --- | --- |
| Unknown connection ID at construction | Throws `Connection not found` |
| Invalid SQL / missing table | `Failed to prepare statement: SQLite failure: ...` |
| `execute()` on a statement that returns rows | Throws `Execute returned rows` |
| Parameter count mismatch | Throws at execution time |
| Method used after `finalize()` | Throws `Statement not found` |

> In offline-write mode (`sqld_offline_mode: true`), `$db->prepare()` is
> not available at all — prepared statements bypass the offline write
> journal (see [offline-writes-sqld-connection.md](offline-writes-sqld-connection.md)).
>
> The cross-cutting throw contract and the complete error-text table are
> collected in [Error Semantics](error-semantics.md).
