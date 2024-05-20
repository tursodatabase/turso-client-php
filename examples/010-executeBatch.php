<?php

// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// SQL statements to execute as a batch
$stmt = "
    INSERT INTO users (name, age) VALUES ('Jane Jenifer', 30);
    INSERT INTO users (name, age) VALUES ('Jane Smith', 25);
    INSERT INTO users (name, age) VALUES ('Michael Johnson', 40);
";

// Execute the batch of SQL statements
if ($db->executeBatch($stmt)) {
    echo "Batch execution successful.";
} else {
    echo "Batch execution failed.";
}

$db->close();

