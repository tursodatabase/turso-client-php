# Offline Writes with sqld Connection (Self-Host libsql-server/sqld)

`sqld` offline mode allows your PHP application to **queue writes locally** in a replica database file and later **synchronize manually** with a remote `sqld` server (or libsql-server compatible backend).

This is ideal for **offline-first apps** that require durability and manual control over when syncing occurs.

---

## Example

```php
<?php

$authToken = "your_auth_token";                 // Replace with valid JWT
$dbUrl = "http://vortexdb.your-own-server.com"; // Remote sqld server

$config = [
    "url" => "file:sqld-offline-write.db", // Local replica file
    "authToken" => $authToken,             // JWT for auth
    "syncUrl" => $dbUrl                    // Remote sqld server URL
];

// Open connection with sqld offline mode
$db = new LibSQL(
    config: $config,
    sqld_offline_mode: true, // Enable sqld offline mode
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true
);

function saveNote(string $content)
{
    global $db, $config;

    // Ensure table exists
    $sql = "CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY,
        content TEXT,
        created_at TEXT
    )";
    $db->execute($sql);

    // Insert new note
    $sql = "INSERT INTO notes (content, created_at) VALUES (?, datetime('now'))";
    $parameters = [$content];
    $db->execute($sql, $parameters);
}

function readNotes()
{
    global $db;

    $sql = "SELECT * FROM notes";
    $result = $db->query($sql);
    $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

    foreach ($rows as $row) {
        echo $row["content"] . PHP_EOL;
    }
}

// Save a random note
$random = bin2hex(random_bytes(16));
$note = "Note $random";
echo "Offline Writing: $note" . PHP_EOL;
saveNote($note);

// Read notes locally
echo "Offline Reading Notes..." . PHP_EOL;
readNotes();

// Simulate a later sync
sleep(5);
echo "Syncing..." . PHP_EOL;

try {
    $db->sync(false); // Manual sync
    echo "Note synced to {$config["syncUrl"]} cloud" . PHP_EOL;
} catch (Exception $e) {
    echo "Note saved locally at {$config["url"]}, will sync later" . PHP_EOL;
}

// Close connection
$db->close();
```

---

## How It Works

1. **Configuration**

   * `url`: Local replica file where offline data is stored.
   * `authToken`: JWT token for authenticating against the remote `sqld`.
   * `syncUrl`: URL of your remote `sqld` service.
   * `sqld_offline_mode: true`: Enables sqld-specific offline handling.
   * `offline_writes: true`: Allows writes even when offline.

2. **Saving Notes (`saveNote`)**

   * Ensures a `notes` table exists.
   * Writes are persisted **locally** into `sqld-offline-write.db`.

3. **Reading Notes (`readNotes`)**

   * Reads are always from the local replica, so data is available offline.

4. **Syncing**

   * `$db->sync(false)` attempts to push changes to the remote.
   * If offline, changes remain stored locally.
   * Next sync attempt will reconcile the replica with the primary.

5. **Closing**

   * `$db->close()` properly closes the replica connection.

---

## Usage

* ✅ **Offline-first apps** where connectivity is unreliable
* ✅ **Edge computing** with periodic sync to the cloud
* ✅ **Testing scenarios** where you simulate offline/online transitions
* ⚠️ Sync is **manual** here → you must call `$db->sync()` at the right time

---

## Best Practices

* Always wrap sync calls in `try/catch`.
* Consider background tasks / cron jobs to call `$db->sync()` periodically.
* Store the local replica (`sqld-offline-write.db`) in persistent storage (volume if inside Docker).
* Use **JWTs with proper expiry & refresh** for secure long-running apps.

---

## Next Steps

* 👉 [Core API](LibSQL-class.md) — learn all available methods
* 👉 [Transactions](transaction.md) — ensure atomic writes
* 👉 [Sync](sync.md) — understand synchronization in detail
