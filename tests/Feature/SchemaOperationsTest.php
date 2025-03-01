<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Schema Operations', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT UNIQUE, age INTEGER CHECK(age >= 18))");
    });

    it('verify table structure', function () {
        $result = $this->db->query("PRAGMA table_info(users)");

        $columns = [];
        foreach ($result->fetchArray(LibSQL::LIBSQL_ASSOC) as $row) {
            $columns[$row['name']] = [
                'type' => $row['type'],
                'notnull' => $row['notnull'],
                'pk' => $row['pk']
            ];
        }

        expect($columns)->toMatchArray([
            'id' => ['type' => 'INTEGER', 'notnull' => 0, 'pk' => 1],
            'name' => ['type' => 'TEXT', 'notnull' => 1, 'pk' => 0],
            'email' => ['type' => 'TEXT', 'notnull' => 0, 'pk' => 0],
            'age' => ['type' => 'INTEGER', 'notnull' => 0, 'pk' => 0]
        ]);
    });

    it('table constraints enforcement', function () {
        // Test UNIQUE constraint
        $this->db->execute("INSERT INTO users (name, email, age) VALUES ('John', 'john@test.com', 25)");
        expect(fn() => $this->db->execute("INSERT INTO users (name, email, age) VALUES ('Jane', 'john@test.com', 30)"))
            ->toThrow(Exception::class);

        // Test CHECK constraint
        expect(fn() => $this->db->execute("INSERT INTO users (name, age) VALUES ('Child', 17)"))
            ->toThrow(Exception::class);

        // Test NOT NULL constraint
        expect(fn() => $this->db->execute("INSERT INTO users (email, age) VALUES ('test@test.com', 20)"))
            ->toThrow(Exception::class);
    });
})->group('SchemaOperationsTest', 'Feature');