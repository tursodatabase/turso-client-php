<?php

$authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDA2Nzc2ODUsIm5iZiI6MTc0MDY3NzY4NSwiZXhwIjoxNzQxMjgyNDg1LCJqdGkiOiJkYjEifQ.6qW2iglFGkiEDZ9IAp0CL5n2zpz_SlD8EwcSDwEurOdQ9d8qrppek5qJ5rXTyH80hyHi5CruaFsvmkcUZg_UBg';

$config = [
    "url" => "file:database.db",
    "authToken" => $authToken,
    "syncUrl" => "http://127.0.0.1:8080",
    "syncInterval" => 5,
    "read_your_writes" => true,
    "encryptionKey" => "",
];

try {
    $db = new LibSQL(
        config: $config,
        flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
        encryption_key: "",
        offline_writes: false
    );

    $db->query("SELECT 1");

    $db->execute("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER)");

    $db->execute("INSERT INTO users (name, age) VALUES ('Bilal Ali Maftullah', 21)");

    $db->query("SELECT * FROM users");

    $db->execute("DROP TABLE users");

    $db->close();

    if (file_exists("database.db")) {
        unlink("database.db");
    }
} catch (\Throwable $th) {
    throw $th;
}

echo "🟩 Remote Replica Database Connection is working fine and thank you!" . PHP_EOL;
