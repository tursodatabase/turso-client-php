<?php

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

echo $db->totalChanges() . PHP_EOL;
echo $db->lastInsertedId() . PHP_EOL;

$users = $db->query("SELECT * FROM users")->fetchArray(LibSQL::LIBSQL_LAZY);

do {
    
    $user = $users->current();
    
    echo "Age: {$user['age']}, Name: {$user['name']}\n";
    
    $users->next();

} while ($users->valid());

$stmt = $db->prepare("SELECT * FROM users WHERE age = ?1");
$stmt->bindPositional([21]);

var_dump($stmt->query()->fetchArray(LibSQL::LIBSQL_ASSOC));

$stmt = $db->prepare("DELETE FROM users WHERE age = :age");
$stmt->bindNamed([':age' => 22]);

var_dump($stmt->execute());

$stmt = $db->prepare("SELECT * FROM users");
$users =$stmt->query()->fetchArray(LibSQL::LIBSQL_LAZY);

do {
    
    $user = $users->current();
    
    echo "Age: {$user['age']}, Name: {$user['name']}\n";
    
    $users->next();

} while ($users->valid());

$db->close();

