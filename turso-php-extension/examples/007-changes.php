<?php

// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

$stmt = "UPDATE users SET age = 28 WHERE id = 1";
$db->execute($stmt);

// Retrieve the number of rows changed
$changes = $db->changes();
echo "Number of Rows Changed: " . $changes;

$db->close();
