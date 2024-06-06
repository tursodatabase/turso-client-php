<?php

// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// Start a new transaction with default behavior
$transaction = $db->transaction();

$transaction->execute("UPDATE users SET name = 'Glauber Costa' WHERE id = 6");

$another_transaction = true;

if ($another_transaction) {
    $transaction->commit();
    echo "Transaction commited!" . PHP_EOL;
} else {
    $transaction->rollback();
    echo "Transaction rollback!" . PHP_EOL;
}

$db->close();

