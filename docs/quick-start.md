# Quick Start

This guide shows how to create a database, insert data, and run queries with **Turso Client PHP** in just a few steps.

---

## 1. Create a New Database

Start with a local database file:

```php
<?php

$libsql = new LibSQL("file:example.db");

// Create a table
$libsql->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
```

---

## 2. Insert Data

```php
// Insert one row
$libsql->execute("INSERT INTO users (name) VALUES (?)", ["Alice"]);

// Insert multiple rows with batch execution
$libsql->executeBatch([
    ["INSERT INTO users (name) VALUES (?)", ["Bob"]],
    ["INSERT INTO users (name) VALUES (?)", ["Charlie"]],
]);
```

---

## 3. Query Data

```php
// Fetch results
$result = $libsql->query("SELECT * FROM users");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
    echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}
```

**Output:**

```
1 - Alice
2 - Bob
3 - Charlie
```

---

## 4. Using Transactions

```php
// Begin a transaction
$tx = $libsql->transaction();

$tx->execute("INSERT INTO users (name) VALUES (?)", ["Diana"]);
$tx->execute("INSERT INTO users (name) VALUES (?)", ["Evan"]);

// Commit changes
$tx->commit();
```

---

## 5. Verify Extension Installation

You can confirm the extension is installed by running:

```bash
php -m | grep libsql
```

---

## Next Steps

* 👉 [Local Connection](local-connection.md) — run SQLite/libSQL locally
* 👉 [Remote Connection](remote-connection.md) — connect to Turso or libSQL server
* 👉 [Core API](LibSQL-class.md) — deep dive into methods and transactions
