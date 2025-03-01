<?php

$db = new LibSQL(":memory:");

if (!$db) {
    throw new Exception("Database Not Connected!");
}

$db->execute("CREATE TABLE products (
    id INTEGER PRIMARY KEY,
    name TEXT,
    price REAL
)");

$stmt = $db->prepare("INSERT INTO products (id, name, price) VALUES (:id, :name, :price)");

echo $stmt->parameterName(1) . PHP_EOL;
echo $stmt->parameterName(2) . PHP_EOL;
echo $stmt->parameterName(3) . PHP_EOL;

$stmt->bindNamed([
    'id' => 1,
    'name' => 'Test',
    'price' => 9.99
]);
$stmt->execute();

$result = $db->query("SELECT name FROM products WHERE id = 1");
$data = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);
print_r($data);

$db->close();
die();
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
$users = $stmt->query()->fetchArray(LibSQL::LIBSQL_LAZY);

do {

    $user = $users->current();

    echo "Age: {$user['age']}, Name: {$user['name']}\n";

    $users->next();

} while ($users->valid());

$db->close();

