<?php

use Tests\TestCase;

uses(TestCase::class);

describe('LibSQLResult', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE test_results (
            id INTEGER PRIMARY KEY,
            name TEXT,
            score REAL,
            active INTEGER
        )");

        $this->db->execute("INSERT INTO test_results (id, name, score, active) VALUES (1, 'Alice', 95.5, 1)");
        $this->db->execute("INSERT INTO test_results (id, name, score, active) VALUES (2, 'Bob', 87.3, 0)");
        $this->db->execute("INSERT INTO test_results (id, name, score, active) VALUES (3, 'Charlie', 92.1, 1)");
    });

    test('columnName returns correct column name by index', function () {
        $result = $this->db->query("SELECT id, name, score FROM test_results LIMIT 1");

        expect($result->columnName(0))->toBe('id')
            ->and($result->columnName(1))->toBe('name')
            ->and($result->columnName(2))->toBe('score');
    });

    test('columnType returns correct column types', function () {
        $result = $this->db->query("SELECT id, name, score, active FROM test_results LIMIT 1");

        expect($result->columnType(0))->toBe('INTEGER')
            ->and($result->columnType(1))->toBe('TEXT')
            ->and($result->columnType(2))->toBe('REAL')
            ->and($result->columnType(3))->toBe('INTEGER');
    });

    test('numColumns returns correct column count', function () {
        $result1 = $this->db->query("SELECT id, name FROM test_results LIMIT 1");
        expect($result1->numColumns())->toBe(2);

        $result2 = $this->db->query("SELECT id, name, score, active FROM test_results LIMIT 1");
        expect($result2->numColumns())->toBe(4);

        $result3 = $this->db->query("SELECT * FROM test_results LIMIT 1");
        expect($result3->numColumns())->toBe(4);
    });

    test('reset clears the result state', function () {
        $result = $this->db->query("SELECT * FROM test_results");

        $firstFetch = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect(count($firstFetch))->toBe(1);

        $result->reset();

        $afterReset = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect(count($afterReset))->toBe(1);
    });

    test('finalize releases result resources', function () {
        $result = $this->db->query("SELECT * FROM test_results");

        $firstFetch = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect(count($firstFetch))->toBe(1);

        $result->finalize();
    });

    test('fetchArray with LIBSQL_ASSOC returns associative array', function () {
        $result = $this->db->query("SELECT id, name, score FROM test_results WHERE id = 1");
        $row = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect(array_keys($row[0]))->toBe(['id', 'name', 'score'])
            ->and($row[0]['name'])->toBe('Alice')
            ->and($row[0]['score'])->toBe(95.5);
    });

    test('fetchArray with LIBSQL_NUM returns numeric array', function () {
        $result = $this->db->query("SELECT id, name FROM test_results WHERE id = 1");
        $row = $result->fetchArray(LibSQL::LIBSQL_NUM);

        expect(array_keys($row[0]))->toBe([0, 1])
            ->and($row[0][0])->toBe(1)
            ->and($row[0][1])->toBe('Alice');
    });

    test('fetchArray with LIBSQL_BOTH returns both associative and numeric', function () {
        $result = $this->db->query("SELECT id, name FROM test_results WHERE id = 1");
        $row = $result->fetchArray(LibSQL::LIBSQL_BOTH);

        expect(count(array_keys($row[0])))->toBe(4)
            ->and($row[0]['name'])->toBe('Alice')
            ->and($row[0][1])->toBe('Alice');
    });

    test('fetchSingle returns single row', function () {
        $result = $this->db->query("SELECT name FROM test_results WHERE id = 1");
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['name'])->toBe('Alice');
    });

    test('fetchArray with LIBSQL_ALL returns all rows', function () {
        $result = $this->db->query("SELECT id, name FROM test_results ORDER BY id");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ALL);

        expect(count($rows))->toBe(3)
            ->and($rows[0]['name'])->toBe('Alice')
            ->and($rows[1]['name'])->toBe('Bob')
            ->and($rows[2]['name'])->toBe('Charlie');
    });

    test('query with parameters works correctly', function () {
        $result = $this->db->query(
            "SELECT name FROM test_results WHERE id = ?",
            [1]
        );
        $row = $result->fetchSingle(LibSQL::LIBSQL_ASSOC);

        expect($row['name'])->toBe('Alice');
    });

    test('empty result set returns empty array', function () {
        $result = $this->db->query("SELECT * FROM test_results WHERE id = 999");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toBe([]);
    });

    test('columnName throws exception for invalid index', function () {
        $result = $this->db->query("SELECT id FROM test_results LIMIT 1");

        expect(fn() => $result->columnName(99))->toThrow(Exception::class);
    });

    test('columnType throws exception for invalid index', function () {
        $result = $this->db->query("SELECT id FROM test_results LIMIT 1");

        expect(fn() => $result->columnType(99))->toThrow(Exception::class);
    });
})->group('LibSQLResultTest', 'Feature');
