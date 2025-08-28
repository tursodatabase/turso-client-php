# Offline Writes with Turso Connection

**Offline writes** allow your PHP application to continue functioning even when the database is temporarily unreachable.
Instead of failing immediately, **writes are stored locally in a replica file** and later synchronized to the primary Turso database once connectivity is restored.

This makes your app **resilient** to network issues — perfect for mobile, edge, or distributed systems.

---

## Example

```php
<?php

$authToken = getenv("TURSO_AUTH_TOKEN");
$dbUrl = getenv("TURSO_DB_URL");

$config = [
    "url" => "file:database.db", // Local replica storage
    "authToken" => $authToken,   // Auth token for Turso
    "syncUrl" => $dbUrl          // Remote Turso DB endpoint
];

// Open connection in offline mode
$db = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key:"",
    offline_writes: true
);

function saveNote(string $content)
{
    global $db;

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

    // Attempt sync to remote Turso
    try {
        $db->sync();
        echo "Note synced to cloud" . PHP_EOL;
    } catch (Exception $e) {
        // If offline, store locally until network is restored
        echo $e->getMessage() . PHP_EOL;
        echo "Note saved locally, will sync later" . PHP_EOL;
    }
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

// Demo: Save a random note and read notes
$random = rand(1, 100);
saveNote("Note $random");
readNotes();
```

---

## How It Works

1. **Configuration**

   * `url`: A local file (`file:database.db`) acts as the replica storage.
   * `authToken`: Your Turso authentication token.
   * `syncUrl`: The remote Turso database endpoint.
   * `offline_writes: true`: Enables local persistence when offline.

2. **Saving Notes** (`saveNote`)

   * Ensures a `notes` table exists.
   * Inserts a new note with a timestamp.
   * Calls `$db->sync()` to push changes to the cloud.
   * If syncing fails (e.g., offline), the note stays local and syncs later.

3. **Reading Notes** (`readNotes`)

   * Fetches all notes from the local replica.
   * Even if offline, you can still read previously stored notes.

4. **Resiliency**

   * Application continues to accept writes.
   * Data is durable locally.
   * Sync automatically restores consistency with the remote when available.

---

## Usage

* ✅ **Mobile apps** or **IoT devices** where connectivity is unreliable
* ✅ **Edge deployments** with temporary offline mode
* ✅ Applications needing **guaranteed durability** even without network access
* ⚠️ Always handle sync errors gracefully (see `try/catch` in example)

---

## Best Practices

* Run periodic `$db->sync()` calls in a background job or cron.
* Monitor sync errors and retry strategies.
* Keep local replica files (`database.db`) in a persistent storage volume.
* For multi-device sync, always rely on the remote Turso DB as the **source of truth**.

---

## Next Steps

* 👉 [Offline Writes (Turso) Connection](offline-writes-turso-connection.md) — Connect libSQL with Offline Writes abillity from (with Turso)
* 👉 [Offline Writes (libSQL Server/sqld) Connection](offline-writes-sqld-connection.md) — Connect libSQL with Offline Writes abillity from (with libSQL Server - self-host)
* 👉 [Core API](LibSQL-class.md) — learn all available methods
* 👉 [Transactions](transaction.md) — ensure atomic writes
* 👉 [Sync](sync.md) — understand synchronization in detail