# Embedded Replica Connection

A **remote replica connection** allows your PHP application to connect to a **read-replica** of a Turso/libSQL database.
This replica can be configured with a **sync URL** so that it periodically synchronizes with the primary database.

---

## Example

```php
<?php

$authToken = 'your_auth_token_key';

$config = [
    "url" => "file:database.db",                   // Local replica database file
    "authToken" => $authToken,                     // Auth token for remote sync
    "syncUrl" => "libsql://my-database.turso.io",  // Primary database endpoint
    "syncInterval" => 5,                           // Sync every 5 seconds
    "read_your_writes" => true,                    // Ensure local reads see recent writes
    "encryptionKey" => "",                         // Optional: set if using encryption
];

// Open a replica connection
$libsql = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: false
);

// Create a table
$libsql->execute("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT)");

// Insert and query
$libsql->execute("INSERT INTO products (name) VALUES (?)", ["Laptop"]);

$result = $libsql->query("SELECT * FROM products");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
  echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}

// You can call sync to sync local embedded database with remote (in background or every starting a program)
$libsql->sync();
```

---

## Usage

* ✅ **Scale read-heavy workloads** by connecting to replicas
* ✅ **Lower latency** by using a replica closer to your users
* ✅ **Automatic synchronization** with the primary via `syncUrl`
* ✅ Supports **read-your-writes consistency** (optional)
* ⚠️ Writes are possible locally but should be used carefully — replicas are designed primarily for **read operations**

---

## Next Steps

* 👉 [Offline Writes (Turso) Connection](offline-writes-turso-connection.md) — Connect libSQL with Offline Writes abillity from (with Turso)
* 👉 [Offline Writes (libSQL Server/sqld) Connection](offline-writes-sqld-connection.md) — Connect libSQL with Offline Writes abillity from (with libSQL Server - self-host)
* 👉 [Core API](LibSQL-class.md) — learn all available methods
* 👉 [Transactions](transaction.md) — ensure atomic writes
* 👉 [Sync](sync.md) — understand synchronization in detail