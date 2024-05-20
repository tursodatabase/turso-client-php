<?php

// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// Check if autocommit mode is enabled
if ($db->isAutocommit()) {
    echo "Autocommit mode is ENABLED." . PHP_EOL;
} else {
    echo "Autocommit mode is DISABLED." . PHP_EOL;
}
$db->close();
