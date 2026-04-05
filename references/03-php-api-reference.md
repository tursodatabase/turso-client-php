# PHP API Reference: Classes, Methods, and Usage

## Class Hierarchy

```
LibSQL (main connection class)
├── LibSQLTransaction (transaction management)
├── LibSQLStatement (prepared statements)
├── LibSQLResult (query results)
└── LibSQLIterator (lazy iteration)
```

---

## LibSQL Class

### Constructor

```php
/**
 * @param string|array $config DSN string or configuration array
 * @param bool $sqld_offline_mode Enable sqld offline mode (self-hosted)
 * @param int $flags Connection flags (default: 6 = READWRITE | CREATE)
 * @param string $encryption_key Database encryption key
 * @param bool $offline_writes Enable offline writes (Turso Cloud)
 */
public function __construct(
    string|array $config,
    bool $sqld_offline_mode = false,
    int $flags = 6,
    string $encryption_key = "",
    bool $offline_writes = false
);
```

### Constants

| Constant | Value | Description |
|----------|-------|-------------|
| `OPEN_READONLY` | 1 | Open database in read-only mode |
| `OPEN_READWRITE` | 2 | Open database in read-write mode |
| `OPEN_CREATE` | 4 | Create database if it doesn't exist |
| `LIBSQL_ASSOC` | 1 | Fetch associative array |
| `LIBSQL_NUM` | 2 | Fetch numeric-indexed array |
| `LIBSQL_BOTH` | 3 | Fetch both (default) |
| `LIBSQL_ALL` | 4 | Fetch all results as array |
| `LIBSQL_LAZY` | 5 | Fetch as LibSQLIterator (Traversable) |

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$mode` | string | Connection mode: `local`, `remote`, `remote_replica`, `offline_write` |
| `$cdc_url` | ?string | Webhook URL for event capture |

### Static Methods

#### `version(): string`

Returns version information for libSQL and the PHP extension.

```php
$version = LibSQL::version();
// "LibSQL Core Version : 3.44.0-3044000 - LibSQL PHP Extension Version: 1.6.2"
```

### Instance Methods

#### `execute(string $stmt, array $parameters = []): int`

Executes a SQL statement and returns the number of affected rows.

```php
// Positional parameters
$rows = $db->execute("INSERT INTO users (name, age) VALUES (?, ?)", ["Alice", 30]);

// Named parameters
$rows = $db->execute(
    "UPDATE users SET name = :name WHERE id = :id",
    [":name" => "Bob", ":id" => 1]
);

// No parameters
$rows = $db->execute("DELETE FROM users WHERE age < 18");
```

**Parameter naming conventions:**
```php
// All valid named parameter formats
[":name" => "Alice"]   // Colon prefix
["@name" => "Alice"]   // At prefix
["$name" => "Alice"]   // Dollar prefix
["name" => "Alice"]    // No prefix (auto-prefixed internally)
```

#### `executeBatch(string $stmt): bool`

Executes multiple SQL statements separated by semicolons.

```php
$success = $db->executeBatch("
    CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT);
    INSERT INTO users (name) VALUES ('Alice');
    INSERT INTO users (name) VALUES ('Bob');
");
```

#### `query(string $stmt, array $parameters = [], bool $force_remote = false): LibSQLResult`

Executes a query and returns a `LibSQLResult` object for fetching data.

```php
$result = $db->query("SELECT * FROM users WHERE age > ?", [18]);
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

// force_remote: Only for sqld offline mode — reads from remote instead of local
$result = $db->query("SELECT * FROM users", [], true);
```

#### `transaction(string $behavior = "DEFERRED"): LibSQLTransaction`

Starts a new transaction.

```php
$tx = $db->transaction();              // DEFERRED (default)
$tx = $db->transaction("DEFERRED");    // Lock on first read/write
$tx = $db->transaction("WRITE");       // IMMEDIATE — lock immediately
$tx = $db->transaction("READ");        // READONLY — read-only transaction
```

#### `prepare(string $sql): LibSQLStatement`

Prepares a SQL statement for repeated execution.

```php
$stmt = $db->prepare("INSERT INTO products (name, price) VALUES (?, ?)");

// Reuse with different parameters
foreach ($products as $product) {
    $stmt->execute([$product['name'], $product['price']]);
    $stmt->reset();  // Clear params for next execution
}

$stmt->finalize();  // Free resources when done
```

#### `close(): void`

Closes the database connection and removes it from the registry.

```php
$db->close();
// After close, using $db will throw "Connection not found"
```

### Sync & Offline Methods

#### `sync(bool $log_info = false): void`

Synchronizes the local database with the remote (for replica and offline modes).

```php
// For remote_replica mode
$db->sync();

// For offline_write mode with logging
$db->sync(true);  // Prints "Sync result: Success" or error message

// Wrapped in try/catch for offline scenarios
try {
    $db->sync();
} catch (Exception $e) {
    echo "Will sync later: " . $e->getMessage();
}
```

#### `checkConnectivity(): bool`

Checks if the remote server is reachable (offline_write modes only).

```php
if ($db->checkConnectivity()) {
    echo "Online — can sync";
} else {
    echo "Offline — changes queued";
}
```

#### `getPendingOperationsCount(): int`

Returns the number of queued operations waiting to sync (offline_write modes only).

```php
$pending = $db->getPendingOperationsCount();
echo "$pending operations waiting to sync";
```

#### `isOnline(): bool`

Returns cached online status (5-second TTL) for offline_write modes.

```php
if ($db->isOnline()) {
    // Cached result — may be up to 5 seconds old
    $db->sync();
}
```

### Metadata Methods

#### `changes(): int`

Returns the number of rows changed by the last statement.

```php
$db->execute("UPDATE users SET age = 30 WHERE id = 1");
echo $db->changes();  // 1
```

#### `totalChanges(): int`

Returns the total number of rows changed since connection.

```php
$db->execute("INSERT INTO users (name) VALUES ('Alice')");
$db->execute("INSERT INTO users (name) VALUES ('Bob')");
echo $db->totalChanges();  // 2
```

#### `lastInsertedId(): int`

Returns the rowid of the last inserted row.

```php
$db->execute("INSERT INTO users (name) VALUES ('Alice')");
$id = $db->lastInsertedId();  // Auto-increment ID
```

#### `isAutocommit(): bool`

Checks if autocommit mode is enabled.

```php
if ($db->isAutocommit()) {
    echo "Each statement is auto-committed";
}
```

### Extension Loading Methods

#### `enableLoadExtension(?bool $onoff): void`

Enables or disables loading of SQLite extensions.

```php
$db->enableLoadExtension(true);   // Enable
$db->enableLoadExtension(false);  // Disable
```

#### `loadExtensions(array|string $extension_paths): void`

Loads one or more SQLite extensions.

```php
// Single extension
$db->loadExtensions("/path/to/extension.so");

// Multiple extensions
$db->loadExtensions([
    "/path/to/extension1.so",
    "/path/to/extension2.so",
]);
```

### Event Capture Method

#### `captureIt(string $event_type, ?string $query, ?string $message): bool`

Sends a webhook event to the configured CDC URL.

```php
$db->captureIt("query_error", "SELECT * FROM users", "Connection timeout");
$db->captureIt("migration", "ALTER TABLE users ADD COLUMN email", "Schema updated");
```

**Webhook payload:**
```json
{
    "event_type": "query_error",
    "query": "SELECT * FROM users",
    "message": "Connection timeout"
}
```

---

## LibSQLTransaction Class

### Constructor

```php
/**
 * @param string $conn_id Database connection ID (internal)
 * @param string $trx_mode Transaction behavior: DEFERRED, WRITE, READ
 */
public function __construct(string $conn_id, string $trx_mode);
```

### Methods

#### `execute(string $stmt, array $parameters = []): int`

Executes a statement within the transaction.

```php
$tx->execute("INSERT INTO accounts (balance) VALUES (1000)");
$tx->execute("UPDATE users SET status = 'active' WHERE id = ?", [1]);
```

#### `query(string $stmt, array $parameters = []): array`

Executes a query within the transaction.

```php
$result = $tx->query("SELECT * FROM accounts WHERE balance > ?", [500]);
```

#### `prepare(string $sql): LibSQLStatement`

Prepares a statement within the transaction.

```php
$stmt = $tx->prepare("INSERT INTO logs (message) VALUES (?)");
$stmt->execute(["Transaction started"]);
```

#### `commit(): void`

Commits the transaction.

```php
$tx->commit();
// Transaction is removed from registry after commit
```

#### `rollback(): void`

Rolls back the transaction.

```php
$tx->rollback();
// All changes since transaction start are undone
```

#### `changes(): int`

Returns rows changed within the transaction.

```php
$tx->execute("UPDATE users SET age = 30 WHERE id = 1");
echo $tx->changes();  // 1
```

#### `isAutocommit(): bool`

Checks autocommit status (should be false during transaction).

```php
$tx->isAutocommit();  // false
```

### Complete Transaction Example

```php
$tx = $db->transaction("DEFERRED");

try {
    // Transfer money between accounts
    $tx->execute("UPDATE accounts SET balance = balance - 100 WHERE id = 1");
    $tx->execute("UPDATE accounts SET balance = balance + 100 WHERE id = 2");

    // Verify balances
    $result = $tx->query("SELECT balance FROM accounts WHERE id IN (1, 2)");
    $balances = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

    $allPositive = array_reduce($balances, fn($carry, $row) =>
        $carry && $row['balance'] >= 0, true
    );

    if ($allPositive) {
        $tx->commit();
        echo "Transfer successful";
    } else {
        $tx->rollback();
        echo "Insufficient funds";
    }
} catch (Exception $e) {
    $tx->rollback();
    echo "Error: " . $e->getMessage();
}
```

---

## LibSQLStatement Class

### Constructor

```php
/**
 * @param string $conn_id Database connection ID (internal)
 * @param string $sql SQL statement with placeholders
 */
public function __construct(string $conn_id, string $sql);
```

### Methods

#### `execute(array $parameters = []): int`

Executes the prepared statement.

```php
$stmt = $db->prepare("INSERT INTO users (name, age) VALUES (?, ?)");
$rows = $stmt->execute(["Alice", 30]);
```

#### `query(array $parameters = []): LibSQLResult`

Executes and returns results.

```php
$stmt = $db->prepare("SELECT * FROM users WHERE age > ?");
$result = $stmt->query([18]);
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
```

#### `bindNamed(array $parameters): void`

Binds named parameters for later execution.

```php
$stmt = $db->prepare("INSERT INTO users (name, age) VALUES (:name, :age)");
$stmt->bindNamed([":name" => "Alice", ":age" => 30]);
$stmt->execute();
```

#### `bindPositional(array $parameters): void`

Binds positional parameters.

```php
$stmt = $db->prepare("INSERT INTO users (name, age) VALUES (?, ?)");
$stmt->bindPositional(["Alice", 30]);
$stmt->execute();
```

#### `reset(): void`

Clears bound parameters for reuse.

```php
$stmt = $db->prepare("INSERT INTO users (name) VALUES (?)");

foreach (["Alice", "Bob", "Charlie"] as $name) {
    $stmt->bindPositional([$name]);
    $stmt->execute();
    $stmt->reset();  // Clear params
}
```

#### `finalize(): void`

Destroys the prepared statement and removes it from the registry.

```php
$stmt->finalize();
// Statement cannot be used after finalize
```

#### `parameterCount(): int`

Returns the number of parameters in the statement.

```php
$stmt = $db->prepare("SELECT * FROM users WHERE name = ? AND age = ?");
echo $stmt->parameterCount();  // 2
```

#### `parameterName(int $idx): ?string`

Returns the name of a parameter by index (1-based).

```php
$stmt = $db->prepare("SELECT * FROM users WHERE name = :name AND age = :age");
echo $stmt->parameterName(1);  // "name"
echo $stmt->parameterName(2);  // "age"
```

#### `columns(): array`

Returns column metadata for the result set.

```php
$stmt = $db->prepare("SELECT id, name FROM users");
$columns = $stmt->columns();
/*
[
    ["name" => "id", "origin_name" => "id", "table_name" => "users", ...],
    ["name" => "name", "origin_name" => "name", "table_name" => "users", ...]
]
*/
```

### Reusable Statement Pattern

```php
// Prepare once
$stmt = $db->prepare("INSERT INTO products (name, price) VALUES (?, ?)");

// Execute multiple times
$products = [
    ["Laptop", 999.99],
    ["Phone", 699.99],
    ["Tablet", 399.99],
];

foreach ($products as $product) {
    $stmt->bindPositional($product);
    $stmt->execute();
    $stmt->reset();  // Important: clear params for next iteration
}

// Clean up when done
$stmt->finalize();
```

---

## LibSQLResult Class

### Constructor

```php
/**
 * @param string $config Connection ID (internal)
 * @param string $sql SQL query that produced this result
 * @param array $parameters Query parameters
 */
public function __construct(string $config, string $sql, array $parameters = []);
```

### Fetch Methods

#### `fetchArray(int $mode = 3): array|LibSQLIterator`

Fetches all rows in the specified format.

```php
$result = $db->query("SELECT id, name FROM users");

// Associative array
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
// [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]

// Numeric array
$rows = $result->fetchArray(LibSQL::LIBSQL_NUM);
// [[0 => 1, 1 => 'Alice'], [0 => 2, 1 => 'Bob']]

// Both
$rows = $result->fetchArray(LibSQL::LIBSQL_BOTH);
// [['id' => 1, 'name' => 'Alice', 0 => 1, 1 => 'Alice'], ...]

// Lazy iterator (memory efficient for large results)
$iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);
foreach ($iterator as $row) {
    // Process one row at a time
}
```

#### `fetchSingle(int $mode = 3): array|LibSQLIterator`

Fetches only the first row.

```php
$result = $db->query("SELECT COUNT(*) FROM users");
$count = $result->fetchSingle(LibSQL::LIBSQL_NUM)[0];  // 42

$result = $db->query("SELECT * FROM users WHERE id = 1");
$user = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);
// ['id' => 1, 'name' => 'Alice']
```

### Column Metadata Methods

#### `columnName(int $column): string`

Returns the name of a column by index.

```php
$result = $db->query("SELECT id, name, email FROM users");
echo $result->columnName(0);  // "id"
echo $result->columnName(1);  // "name"
```

#### `columnType(int $column): string`

Returns the type of a column.

```php
echo $result->columnType(0);  // "Integer" or "Text" etc.
```

#### `numColumns(): int`

Returns the number of columns.

```php
echo $result->numColumns();  // 3
```

### Other Methods

#### `reset(): void`

Resets the result for re-execution.

```php
$result->reset();
```

---

## LibSQLIterator Class

### Purpose
Implements PHP's `Traversable` interface for lazy iteration over large result sets without loading everything into memory.

### Methods

| Method | Return | Description |
|--------|--------|-------------|
| `current()` | mixed | Current element |
| `key()` | int | Current key (0-based index) |
| `next()` | void | Move to next element |
| `rewind()` | void | Reset to first element |
| `valid()` | bool | Check if current position is valid |

### Usage

```php
// Via LIBSQL_LAZY
$result = $db->query("SELECT * FROM large_table");
$iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

foreach ($iterator as $index => $row) {
    echo "Row $index: " . $row['name'] . "\n";
}

// Manual iteration
$iterator->rewind();
while ($iterator->valid()) {
    $row = $iterator->current();
    echo $iterator->key() . ": " . $row['name'] . "\n";
    $iterator->next();
}
```

---

## Connection Configuration Reference

### Local Connection

```php
// Simple file
$db = new LibSQL("database.db");
$db = new LibSQL("file:database.db");

// In-memory
$db = new LibSQL(":memory:");

// With encryption
$db = new LibSQL("file:encrypted.db", false, 6, "secret-key");

// Read-only
$db = new LibSQL("file:readonly.db", false, LibSQL::OPEN_READONLY);
```

### Remote Connection

```php
// DSN format
$db = new LibSQL("libsql:dbname=https://db.turso.io;authToken=tok_abc");

// Config array format
$config = [
    "url" => "https://db.turso.io",
    "authToken" => "tok_abc",
];
$db = new LibSQL($config);
```

### Remote Replica Connection

```php
$config = [
    "url" => "file:database.db",       // Local cache file
    "authToken" => "tok_abc",          // Remote auth
    "syncUrl" => "https://db.turso.io", // Remote endpoint
    "syncInterval" => 5,               // Auto-sync every 5 seconds
    "read_your_writes" => true,        // Consistency guarantee
];

$db = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: false  // Standard replica
);
```

### Offline Write (Turso Cloud)

```php
$config = [
    "url" => "file:database.db",
    "authToken" => "tok_abc",
    "syncUrl" => "https://db.turso.io",
];

$db = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true  // Enable offline writes
);
```

### sqld Offline Write (Self-Hosted)

```php
$config = [
    "url" => "file:database.db",
    "authToken" => "your_jwt_token",
    "syncUrl" => "http://your-sqld-server.com:8080",
];

$db = new LibSQL(
    config: $config,
    sqld_offline_mode: true,   // Enable sqld mode
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true
);
```
