<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Prepared Statements', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE products (
            id INTEGER PRIMARY KEY,
            name TEXT,
            price REAL
        )");
    });

    test('reusable prepared statements', function () {
        $stmt = $this->db->prepare("INSERT INTO products (name, price) VALUES (?, ?)");
        
        $products = [
            ['Laptop', 999.99],
            ['Phone', 699.99],
            ['Tablet', 399.99]
        ];

        foreach ($products as $product) {
            $stmt->bindPositional($product);
            $stmt->execute();
            $stmt->reset();
        }

        $result = $this->db->query("SELECT COUNT(*) FROM products");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(3);
    });

    test('parameter binding validation', function () {
        $stmt = $this->db->prepare("INSERT INTO products (id, name, price) VALUES (:id, :name, :price)");
        
        expect($stmt->parameterCount())->toBe(3)
            ->and($stmt->parameterName(1))->toBe(':id')
            ->and($stmt->parameterName(2))->toBe(':name')
            ->and($stmt->parameterName(3))->toBe(':price');

        $stmt->bindNamed([':id' => 1, ':name' => 'Test', ':price' => 9.99]);
        $stmt->execute();

        $result = $this->db->query("SELECT name FROM products WHERE id = 1");
        expect($result->fetchSingle(LibSQL::LIBSQL_ASSOC)['name'])->toBe('Test');
    });
})->group("PreparedStatementTest", "Feature");