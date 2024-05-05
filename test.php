<?php

$config = [
    "url" => "file:database.db",
    // "authToken" => "secrettoken",
    // "syncUrl" => "libsql://database-org.turso.io",
    // "syncInterval" => null,
    // "encryptionKey" => null
];

$db = new LibSQL($config);
echo $db->version() . PHP_EOL;

// Execute the query
$result = $db->query('SELECT * FROM users WHERE id = :id', [':id' => 1]);

// Check if the query was successful and print the result
if ($result !== false) {
    var_dump($result);
} else {
    echo "Query failed.\n";
}

$db->close();
