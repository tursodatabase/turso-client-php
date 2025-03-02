<?php

try {
    $db = new LibSQL(":memory:");

    $db->query("SELECT 1");

    $db->execute("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER)");

    $db->execute("INSERT INTO users (name, age) VALUES ('Bilal Ali Maftullah', 21)");

    $db->query("SELECT * FROM users");
} catch (\Throwable $th) {
    throw $th;
}

echo "🟩 In-Memory Database Connection is working fine and thank you!" . PHP_EOL;