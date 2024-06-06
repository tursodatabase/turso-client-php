<?php

$db = new LibSQL("libsql:dbname=database.db");

$results = $db->query("SELECT * FROM users");

foreach ($results->fetchArray() as $row) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . "\n";
}

$db->close();
