<?php

// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// SQL statement with positional parameters
$stmt = "INSERT INTO users (name, age) VALUES (?, ?)";
$parameters = ["John Doe", 30];
$rowsAffected = $db->execute($stmt, $parameters);
echo "Inserted $rowsAffected rows." . PHP_EOL;

$results = $db->query("SELECT * FROM users");

foreach ($results['rows'] as $row) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . PHP_EOL;
}

// SQL statement with named parameters
$stmt = "UPDATE users SET name = :name WHERE id = :id";
$parameters = [":name" => "Jane Doe", ":id" => 6];
$rowsAffected = $db->execute($stmt, $parameters);
echo "Updated $rowsAffected rows." . PHP_EOL;

$results = $db->query("SELECT * FROM users");

foreach ($results['rows'] as $row) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . PHP_EOL;
}

$db->close();

