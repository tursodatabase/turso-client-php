<?php

$authToken = getenv("TURSO_AUTH_TOKEN");
$dbUrl = getenv("TURSO_DB_URL");

$config = [
    "url" => "file:database.db",
    "authToken" => $authToken,
    "syncUrl" => $dbUrl
];

$db = new LibSQL(
    config: $config,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key:"",
    offline_writes: true
);

function saveNote(string $content)
{
    global $db;

    $sql = "CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY,
        content TEXT,
        created_at TEXT
    )";
    $db->execute($sql);

    $sql = "INSERT INTO notes (content, created_at) VALUES (?, datetime('now'))";
    $parameters = [$content];
    $db->execute($sql, $parameters);

    try {
        $db->sync();
        echo "Note synced to cloud" . PHP_EOL;
    } catch (Exception $e) {
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

$random = rand(1, 100);
saveNote("Note $random");
readNotes();