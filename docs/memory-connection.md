# In-Memory Connection

An **in-memory connection** creates a temporary database stored entirely in memory.
This database disappears when the script ends.

---

## Example

```php
<?php

// Connect to an in-memory database
$libsql = new LibSQL("file::memory:");

// Create schema
$libsql->execute("CREATE TABLE sessions (id INTEGER PRIMARY KEY, token TEXT)");

// Insert data
$libsql->execute("INSERT INTO sessions (token) VALUES (?)", ["abc123"]);

// Query data
$result = $libsql->query("SELECT * FROM users");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
  echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}
```

---

## When to Use

* Unit testing
* Temporary datasets
* Caching scenarios where persistence isn’t needed

---

## Next Steps

* 👉 [Remote Connection](remote-connection.md) — Connect libSQL remotely
* 👉 [Embedded Replica Connection](embedded-replica-connection.md) — Connect libSQL with Embedded Replica
* 👉 [Offline Writes (Turso) Connection](offline-writes-turso-connection.md) — Connect libSQL with Offline Writes abillity from (with Turso)
* 👉 [Offline Writes (libSQL Server/sqld) Connection](offline-writes-sqld-connection.md) — Connect libSQL with Offline Writes abillity from (with libSQL Server - self-host)