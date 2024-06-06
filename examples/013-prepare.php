<?php

// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// Prepare an SQL statement for execution
$sql = "SELECT * FROM users WHERE id = ?";
$statement = $db->prepare($sql);

if ($statement) {
    // Execute the prepared statement with parameters
    $result = $statement->query([1])->fetchArray();
    var_dump($result);
} else {
    // Handle error
    echo "Failed to prepare statement.";
}

$db->close();
