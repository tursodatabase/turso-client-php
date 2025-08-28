<?php

try {
    $db = new LibSQL("database.db");

    $db->query("SELECT 1");

    $db->execute("CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, age INTEGER)");

    $db->execute("INSERT INTO users (name, age) VALUES ('Bilal Ali Maftullah', 21)");

    $query = $db->query("SELECT * FROM users");

    foreach ($query->fetchArray(LibSQL::LIBSQL_ASSOC) as $row ) {
        echo "Name: {$row['name']}, Age: {$row['age']}" . PHP_EOL;
    }
} catch (\Throwable $th) {
    throw $th;
}

echo "🟩 Local Database Connection is working fine and thank you!" . PHP_EOL;

foreach (glob('*.db*') as $file) {
    unlink($file);
}
