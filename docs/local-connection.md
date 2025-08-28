# Local Connection

A **local connection** is the simplest way to use Turso Client PHP.
It stores data in a local SQLite/libSQL file on disk.

---

## Example

```php
<?php

// Connect to a local database file
$libsql = new LibSQL("file:database.db");

// Create a table
$libsql->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

// Insert data
$libsql->execute("INSERT INTO users (name) VALUES (?)", ["Alice"]);

// Query
$result = $libsql->query("SELECT * FROM users");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
  echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}
```

---

## When to Use

* Prototyping or small projects
* Local development with SQLite semantics
* Applications that don’t need replication or remote access

---

## Next Steps

* 👉 [Memory Connection](memory-connection.md) — Connect libSQL in-memory
* 👉 [Remote Connection](remote-connection.md) — Connect libSQL remotely
* 👉 [Embedded Replica Connection](embedded-replica-connection.md) — Connect libSQL with Embedded Replica
* 👉 [Offline Writes (Turso) Connection](offline-writes-turso-connection.md) — Connect libSQL with Offline Writes abillity from (with Turso)
* 👉 [Offline Writes (libSQL Server/sqld) Connection](offline-writes-sqld-connection.md) — Connect libSQL with Offline Writes abillity from (with libSQL Server - self-host)