<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Edge Cases and Error Handling', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE edge_test (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            email TEXT UNIQUE,
            age INTEGER CHECK(age >= 0)
        )");
    });

    test('execute with invalid SQL throws exception', function () {
        expect(fn() => $this->db->execute("INVALID SQL STATEMENT HERE"))->toThrow(Exception::class);
    });

    test('execute with syntax error throws exception', function () {
        expect(fn() => $this->db->execute("SELECT * FORM table"))->toThrow(Exception::class);
    });

    test('query with non-existent table throws exception', function () {
        expect(fn() => $this->db->query("SELECT * FROM non_existent_table"))->toThrow(Exception::class);
    });

    test('NOT NULL constraint violation throws exception', function () {
        expect(fn() => $this->db->execute("INSERT INTO edge_test (id) VALUES (1)"))->toThrow(Exception::class);
    });

    test('UNIQUE constraint violation throws exception', function () {
        $this->db->execute("INSERT INTO edge_test (id, name, email) VALUES (1, 'Alice', 'alice@example.com')");

        expect(fn() => $this->db->execute("INSERT INTO edge_test (id, name, email) VALUES (2, 'Bob', 'alice@example.com')"))
            ->toThrow(Exception::class);
    });

    test('CHECK constraint violation throws exception', function () {
        expect(fn() => $this->db->execute("INSERT INTO edge_test (id, name, age) VALUES (1, 'Test', -5)"))
            ->toThrow(Exception::class);
    });

    test('prepare with invalid SQL throws exception', function () {
        expect(fn() => $this->db->prepare("INVALID SQL"))->toThrow(Exception::class);
    });

    test('executeBatch with invalid SQL throws exception', function () {
        expect(fn() => $this->db->executeBatch("INVALID BATCH STATEMENT"))->toThrow(Exception::class);
    });

    test('parameter type handling', function () {
        $this->db->execute("INSERT INTO edge_test (id, name, age) VALUES (1, 'Test', 25)");

        $result = $this->db->query("SELECT * FROM edge_test WHERE id = ?", [1]);
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['id'])->toBe(1)
            ->and($row['name'])->toBe('Test')
            ->and($row['age'])->toBe(25);
    });

    test('null value handling', function () {
        $this->db->execute("INSERT INTO edge_test (id, name, email) VALUES (1, 'Test', NULL)");

        $result = $this->db->query("SELECT email FROM edge_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['email'])->toBeNull();
    });

    test('empty string handling', function () {
        $this->db->execute("INSERT INTO edge_test (id, name, email) VALUES (1, 'Test', '')");

        $result = $this->db->query("SELECT email FROM edge_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['email'])->toBe('');
    });

    test('special characters in strings', function () {
        $specialChars = "Hello 'World' with \"quotes\" and \\backslash\\ and <html> & <tags>";

        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (1, ?)", [$specialChars]);

        $result = $this->db->query("SELECT name FROM edge_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['name'])->toBe($specialChars);
    });

    test('unicode characters handling', function () {
        $unicode = "Hello 世界 🌍 café naïve";

        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (1, ?)", [$unicode]);

        $result = $this->db->query("SELECT name FROM edge_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['name'])->toBe($unicode);
    });

    test('large integer handling', function () {
        $largeInt = 9223372036854775807;

        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (1, 'large')");

        $result = $this->db->query("SELECT id FROM edge_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_NUM);

        expect($row[0])->toBe(1);
    });

    test('float precision handling', function () {
        $float = 3.14159265358979;

        $this->db->execute("CREATE TABLE float_test (id INTEGER PRIMARY KEY, value REAL)");
        $this->db->execute("INSERT INTO float_test (id, value) VALUES (1, ?)", [$float]);

        $result = $this->db->query("SELECT value FROM float_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_NUM);

        expect($row[0])->toBeApproximate(3.14159265358979, 0.0000001);
    });

    test('close connection prevents further operations', function () {
        $this->db->close();

        expect(fn() => $this->db->execute("SELECT 1"))->toThrow(Exception::class);
    });

    test('multiple close calls do not throw', function () {
        $this->db->close();
        expect(fn() => $this->db->close())->not->toThrow(Exception::class);
    });

    test('transaction commit after close throws exception', function () {
        $this->db->close();

        expect(fn() => $this->db->transaction())->toThrow(Exception::class);
    });

    test('empty result set from query', function () {
        $result = $this->db->query("SELECT * FROM edge_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toBe([]);
    });

    test('query with empty parameter array', function () {
        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (1, 'Test')");

        $result = $this->db->query("SELECT * FROM edge_test WHERE id = 1", []);
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect(count($rows))->toBe(1);
    });

    test('consecutive inserts maintain correct order', function () {
        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (1, 'First')");
        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (2, 'Second')");
        $this->db->execute("INSERT INTO edge_test (id, name) VALUES (3, 'Third')");

        $result = $this->db->query("SELECT name FROM edge_test ORDER BY id");
        $rows = $result->fetchArray(LibSQL::LIBSQL_NUM);

        expect($rows[0][0])->toBe('First')
            ->and($rows[1][0])->toBe('Second')
            ->and($rows[2][0])->toBe('Third');
    });

    test('table drop and recreate works', function () {
        $this->db->execute("DROP TABLE edge_test");

        $this->db->execute("CREATE TABLE edge_test (id INTEGER PRIMARY KEY, value TEXT)");

        $this->db->execute("INSERT INTO edge_test (id, value) VALUES (1, 'New')");

        $result = $this->db->query("SELECT value FROM edge_test WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['value'])->toBe('New');
    });
})->group('EdgeCasesTest', 'Feature');
