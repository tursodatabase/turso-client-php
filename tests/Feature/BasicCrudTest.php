<?php

use Tests\TestCase;

uses(TestCase::class);

describe('CRUD Operations', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT,
            email TEXT,
            age INTEGER
        )");
    });

    test('basic insert and retrieve', function () {
        $insertCount = $this->db->execute(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['Alice', 'alice@example.com', 28]
        );

        expect($insertCount)->toBe(1)
            ->and($this->db->lastInsertedId())->toBe(1);

        $result = $this->db->query("SELECT * FROM users WHERE id = 1");
        $user = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($user[0])->toMatchArray([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 28
        ]);
    });

    test('parameter binding types', function () {
        // Test named parameters
        $this->db->execute(
            "INSERT INTO users (name, age) VALUES (:name, :age)",
            [':name' => 'Bob', ':age' => 35]
        );

        // Test positional parameters
        $this->db->execute(
            "UPDATE users SET email = ? WHERE id = ?",
            ['bob@example.com', 1]
        );

        $result = $this->db->query("SELECT * FROM users");
        expect($result->numColumns())->toBe(4);
    });

    test('update and delete operations', function () {
        // Initial insert
        $this->db->execute(
            "INSERT INTO users (name, email, age) VALUES (?, ?, ?)",
            ['Charlie', 'charlie@example.com', 40]
        );

        // Update
        $updateCount = $this->db->execute(
            "UPDATE users SET age = ? WHERE id = ?",
            [41, 1]
        );
        expect($updateCount)->toBe(1);

        // Delete
        $deleteCount = $this->db->execute("DELETE FROM users WHERE id = 1");
        $deletedUser = $this->db->query("SELECT COUNT(*) FROM users")
            ->fetchSingle(LibSQL::LIBSQL_NUM);
        expect($deleteCount)->toBe(1)
            ->and($deletedUser[0])->toBe(0);
    });
})->group("BasicCrudTest", "Feature");
