<?php

function testLibSQL($dsn, $expected) {
    try {
        new LibSQL($dsn);
        echo "🟩 expected DSN: $dsn\n";
    } catch (Exception $e) {
        if ($expected === 'fail') {
            echo "🟥 failed for DSN: $dsn\n";
        } else {
            echo "🟥 failed unexpectedly for DSN: $dsn\n";
        }
    }
}

testLibSQL(":memory:", 'pass');
testLibSQL("file:memory", 'fail');
testLibSQL("libsql:memory", 'fail');
testLibSQL("", 'fail');

# This will create a file named "memory" and become local database file
testLibSQL("memory", 'fail');
if (file_exists('memory')) {
    echo "Test failed unexpectedly for in-memory connection\n";
    unlink('memory');
}