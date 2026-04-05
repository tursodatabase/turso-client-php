<?php

use Tests\TestCase;

uses(TestCase::class);

describe('LibSQLStatement', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE items (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            price REAL,
            category TEXT
        )");

        $this->db->execute("INSERT INTO items (id, name, price, category) VALUES (1, 'Laptop', 999.99, 'Electronics')");
        $this->db->execute("INSERT INTO items (id, name, price, category) VALUES (2, 'Phone', 699.99, 'Electronics')");
        $this->db->execute("INSERT INTO items (id, name, price, category) VALUES (3, 'Desk', 299.50, 'Furniture')");
    });

    test('finalize releases statement resources', function () {
        $stmt = $this->db->prepare("SELECT * FROM items WHERE id = ?");

        $result = $stmt->execute([1]);
        expect($result)->toBe(1);

        $stmt->finalize();
    });

    test('query returns LibSQLResult', function () {
        $stmt = $this->db->prepare("SELECT name, price FROM items WHERE category = ?");

        $result = $stmt->query(['Electronics']);
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect(count($rows))->toBe(2)
            ->and($rows[0]['name'])->toBe('Laptop')
            ->and($rows[1]['name'])->toBe('Phone');
    });

    test('columns returns column metadata', function () {
        $stmt = $this->db->prepare("SELECT id, name, price FROM items LIMIT 1");

        $columns = $stmt->columns();

        expect(count($columns))->toBe(3)
            ->and($columns[0]['name'])->toBe('id')
            ->and($columns[1]['name'])->toBe('name')
            ->and($columns[2]['name'])->toBe('price');
    });

    test('execute returns affected row count', function () {
        $stmt = $this->db->prepare("UPDATE items SET price = ? WHERE id = ?");

        $affected = $stmt->execute([1099.99, 1]);
        expect($affected)->toBe(1);

        $result = $this->db->query("SELECT price FROM items WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);
        expect($row['price'])->toBe(1099.99);
    });

    test('bindPositional and execute workflow', function () {
        $stmt = $this->db->prepare("INSERT INTO items (id, name, price, category) VALUES (?, ?, ?, ?)");

        $stmt->bindPositional([4, 'Chair', 149.99, 'Furniture']);
        $affected = $stmt->execute();
        expect($affected)->toBe(1);

        $stmt->reset();

        $stmt->bindPositional([5, 'Lamp', 79.99, 'Furniture']);
        $affected = $stmt->execute();
        expect($affected)->toBe(1);

        $result = $this->db->query("SELECT COUNT(*) FROM items WHERE category = 'Furniture'");
        $count = $result->fetchSingle(LibSQL::LIBSQL_NUM)[0];
        expect($count)->toBe(3);
    });

    test('bindNamed and execute workflow', function () {
        $stmt = $this->db->prepare("INSERT INTO items (id, name, price, category) VALUES (:id, :name, :price, :category)");

        $stmt->bindNamed([':id' => 6, ':name' => 'Bookshelf', ':price' => 199.99, ':category' => 'Furniture']);
        $affected = $stmt->execute();
        expect($affected)->toBe(1);

        $result = $this->db->query("SELECT name FROM items WHERE id = 6");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);
        expect($row['name'])->toBe('Bookshelf');
    });

    test('parameterCount returns correct count', function () {
        $stmt1 = $this->db->prepare("SELECT * FROM items WHERE id = ?");
        expect($stmt1->parameterCount())->toBe(1);

        $stmt2 = $this->db->prepare("SELECT * FROM items WHERE name = ? AND price = ?");
        expect($stmt2->parameterCount())->toBe(2);

        $stmt3 = $this->db->prepare("INSERT INTO items (id, name, price) VALUES (:id, :name, :price)");
        expect($stmt3->parameterCount())->toBe(3);
    });

    test('parameterName returns parameter names for named params', function () {
        $stmt = $this->db->prepare("SELECT * FROM items WHERE name = :name AND price = :price");

        expect($stmt->parameterName(1))->toBe('name')
            ->and($stmt->parameterName(2))->toBe('price');
    });

    test('reset clears bound parameters', function () {
        $stmt = $this->db->prepare("INSERT INTO items (id, name) VALUES (?, ?)");

        $stmt->bindPositional([10, 'Test']);
        $stmt->execute();

        $stmt->reset();

        $stmt->bindPositional([11, 'Test2']);
        $stmt->execute();

        $result = $this->db->query("SELECT COUNT(*) FROM items WHERE id IN (10, 11)");
        $count = $result->fetchSingle(LibSQL::LIBSQL_NUM)[0];
        expect($count)->toBe(2);
    });

    test('multiple executions with different parameters', function () {
        $stmt = $this->db->prepare("SELECT name FROM items WHERE id = ?");

        $result1 = $stmt->query([1]);
        expect($result1->fetchSingle(LibSQL::LIBSQL_ASSOC)['name'])->toBe('Laptop');

        $result2 = $stmt->query([2]);
        expect($result2->fetchSingle(LibSQL::LIBSQL_ASSOC)['name'])->toBe('Phone');

        $result3 = $stmt->query([3]);
        expect($result3->fetchSingle(LibSQL::LIBSQL_ASSOC)['name'])->toBe('Desk');
    });

    test('prepare with invalid SQL throws exception', function () {
        expect(fn() => $this->db->prepare("INVALID SQL STATEMENT"))->toThrow(Exception::class);
    });
})->group('LibSQLStatementTest', 'Feature');
