# LibSQLTransaction Class

`LibSQLTransaction` represents an **active transaction** on a connection.
You create one through `$db->transaction($behavior)` — the class
constructor requires an internal connection ID that is not exposed as a
public property, so treat it the same way.

Transaction objects do not leak native resources: a transaction that is
abandoned (neither committed nor rolled back) is rolled back by its
destructor when the PHP object is garbage collected, so open `BEGIN` blocks
no longer last for the life of the process.

---

## Class Synopsis

```php
class LibSQLTransaction
{
    public function __construct(string $conn_id, string $trx_mode);
    public function changes(): int;
    public function isAutocommit(): bool;
    public function execute(string $stmt, array $parameters = []): int;
    public function prepare(string $sql): LibSQLStatement;
    public function query(string $stmt, array $parameters): array;
    public function commit(): void;
    public function rollback(): void;
}
```

---

### `__construct(string $conn_id, string $trx_mode)`

Created through `$db->transaction()`. The behavior string is matched after
upper-casing:

| Value | SQLite behavior |
| --- | --- |
| `WRITE` | `IMMEDIATE` — write locks taken at transaction start |
| `READ` | `READ ONLY` |
| anything else (including `DEFERRED`) | `DEFERRED` — SQLite default |

```php
$trx = $db->transaction('WRITE'); // or 'DEFERRED' / 'READ'
```

If SQLite refuses to begin the transaction (lock contention, and so on)
the constructor throws `Failed to begin transaction: ...`.

### `changes(): int`

Rows changed by the **most recent statement inside the transaction**:

```php
$trx->execute("INSERT INTO users (name) VALUES ('One')");
$trx->changes(); // 1
```

### `isAutocommit(): bool`

`false` while the transaction is open (the statement runs outside
autocommit), back to `true` on the connection after `commit()`:

```php
$trx->isAutocommit(); // false (inside the transaction)
$trx->commit();
$db->isAutocommit();  // true
```

### `execute(string $stmt, array $parameters = []): int`

Runs DML inside the transaction and returns the number of affected rows —
same semantics as `$db->execute()`, so SELECTs are rejected:

```php
$trx->execute(
    "INSERT INTO users (name, age) VALUES (?, ?)",
    ['Grace', 45]
); // 1
```

### `prepare(string $sql): LibSQLStatement`

Prepares a statement on the same connection — running it still happens
**inside the transaction**:

```php
$st = $trx->prepare("INSERT INTO users VALUES (?, ?)");
$st->bindPositional([2, 'Bob']);
$st->execute();
$trx->commit(); // both inserts (execute() + prepare()) commit together
```

### `query(string $stmt, array $parameters): array`

Runs a query inside the transaction. **The parameters argument is
required** (there is no default). It does not return a result object — the
return value is a plain structured array:

```php
$trx->query("SELECT id, name FROM users", []);
// [
//   "columns"           => ["id", "name"],
//   "rows"              => [["id" => 1, "name" => "Ada"], ...],
//   "rows_affected"     => 1,
//   "last_insert_rowid" => 1,
// ]
```

### `commit(): void`

Ends the transaction. Calling `commit()` a second time throws
`Transaction not found` — the registry entry is removed by the first
successful commit.

### `rollback(): void`

Cancels everything the transaction did:

```php
$trx->execute("INSERT INTO users VALUES (?, ?)", [99, 'Oops']);
$trx->rollback(); // the INSERT is undone
```

A second call also throws `Transaction not found`. And an *abandoned*
transaction (no commit, no rollback) is rolled back automatically by the
object's destructor at GC time.

---

## Error semantics

| Situation | Result |
| --- | --- |
| `commit()` / `rollback()` on an already-finished transaction | Throws `Transaction not found` |
| `execute()` on a SELECT that returns rows | Throws `Execute returned rows` |
| `BEGIN` fails (lock contention, and so on) | Throws `Failed to begin transaction: ...` |
| Abandoned transaction collected by GC | Automatic rollback (no throw) |

> In offline-write mode (`sqld_offline_mode: true`), `$db->transaction()`
> is not available at all — explicit transactions bypass the offline write
> journal (see [offline-writes-sqld-connection.md](offline-writes-sqld-connection.md)).
