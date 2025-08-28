# Remote Connection

A **remote connection** allows PHP applications to connect to a **Turso** or **libSQL** server running remotely.
This is how you scale beyond local SQLite.

---

## Example

```php
<?php

// Replace with your Turso/libSQL server URL and auth token
$dbUrl = "libsql://my-database.turso.io";
$authToken = "your_auth_token";

// Connect to remote database
$libsql = new LibSQL("libsql:dbname=$dbUrl;authToken=$authToken");

// Create a table remotely
$libsql->execute("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT)");

// Insert and query
$libsql->execute("INSERT INTO products (name) VALUES (?)", ["Laptop"]);

$result = $libsql->query("SELECT * FROM products");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
  echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}
```

---

## When to Use

* Production environments
* Multi-region deployments with Turso
* Applications needing centralized storage

---

## Next Steps

* 👉 [Embedded Replica Connection](embedded-replica-connection.md) — Connect libSQL with Embedded Replica
* 👉 [Offline Writes (Turso) Connection](offline-writes-turso-connection.md) — Connect libSQL with Offline Writes abillity from (with Turso)
* 👉 [Offline Writes (libSQL Server/sqld) Connection](offline-writes-sqld-connection.md) — Connect libSQL with Offline Writes abillity from (with libSQL Server - self-host)