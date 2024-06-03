<?php

use Doctrine\DBAL\DriverManager;

require_once __DIR__ . '/../vendor/autoload.php';

$params = [
    "url"               => ":memory:",
    'driverClass'       => \Darkterminal\LibSQL\DBAL\Driver::class,
];

$db = DriverManager::getConnection($params);

$createTable = "CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    age INTEGER
)";
$db->executeStatement($createTable);

$insertUsers = "INSERT INTO users (name, age) VALUES ('Budi Dalton', 49);
INSERT INTO users (name, age) VALUES ('Sujiwo Tedjo', 50);";

$db->getNativeConnection()->executeBatch($insertUsers);

$result = $db->executeQuery("SELECT * FROM users")->fetchAllAssociative();
var_dump($result);
$db->close();
