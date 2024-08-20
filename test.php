<?php

// Noting here

$db = new LibSQL(":memory:");

if (!$db) {
    throw new Exception("Database Not Connected!");
}

$createUsers = <<<STMT
CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    age INTEGER
);
INSERT INTO users (name, age) VALUES ('Bilal Ali Maftullah', 21);
INSERT INTO users (name, age) VALUES ('Lisa Nur Amelia', 22);
STMT;

$db->executeBatch($createUsers);

$users = $db->query("SELECT * FROM users")->fetchArray(LibSQL::LIBSQL_LAZY);

do {
    
    $user = $users->current();
    
    echo "Age: {$user['age']}, Name: {$user['name']}\n";
    
    $users->next();

} while ($users->valid());
