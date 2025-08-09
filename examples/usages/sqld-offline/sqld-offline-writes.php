<?php

$authToken = "eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSIsImlzX2dyb3VwIjoibm8ifQ.eyJpYXQiOjE3NTM3Njk0NzQsIm5iZiI6MTc1Mzc2OTQ3NCwiZXhwIjo0OTA5NDQzMDc0LCJqdGkiOiJ2b3J0ZXhkYiIsImlkIjoidm9ydGV4ZGIiLCJ1aWQiOjEsImdpZCI6Im5vbmUifQ.5T7PKH8-n7c3V5XWHDrnOPUB3iiKpOPNJxDRzkJUXc0Jxe-GdbgBAiocZ9cXCfgdHMrG-9sb2UKs2-Kqd_1FBQ";
$dbUrl = "http://vortexdb.localhost:8080";

$config = [
    "url" => "file:sqld-offline-write.db",
    "authToken" => $authToken,
    "syncUrl" => $dbUrl
];

$db = new LibSQL(
    config: $config,
    sqld_offline_mode: true,
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true
);

function saveNote(string $content)
{
    global $db, $config;

    $sql = "CREATE TABLE IF NOT EXISTS notes (
        id INTEGER PRIMARY KEY,
        content TEXT,
        created_at TEXT
    )";
    $db->execute($sql);

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

$random = rand(1, 100);
$note = "Note $random";
echo "Offline Writing: $note" . PHP_EOL;
saveNote($note);

echo "Offline Reading Notes..." . PHP_EOL;
readNotes();

sleep(5); // simulate sync later

echo "Syncing..." . PHP_EOL;
try {
    $db->sync(false);
    echo "Note synced to {$config["syncUrl"]} cloud" . PHP_EOL;
} catch (Exception $e) {
    echo "Note saved locally at {$config["url"]}, will sync later" . PHP_EOL;
}

$db->close();
