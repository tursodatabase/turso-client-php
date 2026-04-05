<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Batch Operations', function () {
    test('execute multiple statements', function () {
        $batch = "
            CREATE TABLE cities (
                id INTEGER PRIMARY KEY,
                name TEXT,
                population INTEGER
            );

            INSERT INTO cities (name, population) VALUES
                ('Paris', 2161000),
                ('London', 8982000),
                ('Berlin', 3769000);
        ";

        $success = $this->db->executeBatch($batch);
        expect($success)->toBeTrue();

        $result = $this->db->query("SELECT COUNT(*) FROM cities");
        expect($result->fetchArray(LibSQL::LIBSQL_NUM)[0][0])->toBe(3);
    });

    test('execute prepared statement with multiple parameter sets', function () {
        $this->db->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");

        $stmt = $this->db->prepare("INSERT INTO users (name, email) VALUES (?, ?)");

        $parameterSets = [
            ['Alice', 'alice@example.com'],
            ['Bob', 'bob@example.com'],
            ['Charlie', 'charlie@example.com'],
        ];

        $totalRows = $stmt->executeBatch($parameterSets);
        expect($totalRows)->toBe(3);

        $result = $this->db->query("SELECT COUNT(*) FROM users");
        expect($result->fetchArray(LibSQL::LIBSQL_NUM)[0][0])->toBe(3);
    });

    test('execute prepared statement with named parameter sets', function () {
        $this->db->execute("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL)");

        $stmt = $this->db->prepare("INSERT INTO products (name, price) VALUES (:name, :price)");

        $parameterSets = [
            [':name' => 'Widget', ':price' => 9.99],
            [':name' => 'Gadget', ':price' => 24.99],
            [':name' => 'Doohickey', ':price' => 14.99],
        ];

        $totalRows = $stmt->executeBatch($parameterSets);
        expect($totalRows)->toBe(3);

        $result = $this->db->query("SELECT COUNT(*) FROM products");
        expect($result->fetchArray(LibSQL::LIBSQL_NUM)[0][0])->toBe(3);

        $result = $this->db->query("SELECT * FROM products WHERE name = 'Gadget'");
        $row = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($row[0]['price'])->toBe(24.99);
    });
})->group('BatchOperationTest', 'Feature');
