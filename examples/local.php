<?php

function testLibSQL($dsn, $expected) {
    try {
        new LibSQL($dsn);
        echo "\e[32mTest passed for DSN: $dsn\e[0m\n";
    } catch (Exception $e) {
        if ($expected === 'fail') {
            echo "\e[31mTest correctly failed for DSN: $dsn\e[0m\n";
        } else {
            echo "\e[31mTest failed unexpectedly for DSN: $dsn\e[0m\n";
        }
    }
}

testLibSQL("libsql:dbname=database.db", 'pass');
testLibSQL("database.db", 'pass');
testLibSQL("file:database.db", 'pass');
testLibSQL("libsql:database.db", 'fail');
testLibSQL("", 'fail');

unlink('database.db');