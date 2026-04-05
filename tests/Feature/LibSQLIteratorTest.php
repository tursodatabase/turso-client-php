<?php

use Tests\TestCase;

uses(TestCase::class);

describe('LibSQLIterator', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE iterator_test (
            id INTEGER PRIMARY KEY,
            value TEXT
        )");

        $this->db->execute("INSERT INTO iterator_test (id, value) VALUES (1, 'alpha')");
        $this->db->execute("INSERT INTO iterator_test (id, value) VALUES (2, 'beta')");
        $this->db->execute("INSERT INTO iterator_test (id, value) VALUES (3, 'gamma')");
        $this->db->execute("INSERT INTO iterator_test (id, value) VALUES (4, 'delta')");
        $this->db->execute("INSERT INTO iterator_test (id, value) VALUES (5, 'epsilon')");
    });

    test('iterator implements Iterator interface correctly', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        expect($iterator)->toBeInstanceOf(LibSQLIterator::class);
    });

    test('iterator rewind resets to first element', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        $current = $iterator->current();

        expect($current['id'])->toBe(1)
            ->and($current['value'])->toBe('alpha');
    });

    test('iterator current returns current element', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        $current = $iterator->current();

        expect($current)->toBeArray()
            ->and($current['id'])->toBe(1);
    });

    test('iterator key returns current position', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        $key = $iterator->key();

        expect($key)->toBe(0);
    });

    test('iterator next advances to next element', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        $first = $iterator->current();
        expect($first['id'])->toBe(1);

        $iterator->next();
        $second = $iterator->current();
        expect($second['id'])->toBe(2);

        $iterator->next();
        $third = $iterator->current();
        expect($third['id'])->toBe(3);
    });

    test('iterator valid returns true while elements exist', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        expect($iterator->valid())->toBeTrue();

        // Advance through all elements
        for ($i = 0; $i < 5; $i++) {
            $iterator->next();
        }
    });

    test('iterator can be used in foreach loop', function () {
        $result = $this->db->query("SELECT value FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $values = [];
        foreach ($iterator as $row) {
            $values[] = $row['value'];
        }

        expect($values)->toBe(['alpha', 'beta', 'gamma', 'delta', 'epsilon']);
    });

    test('iterator handles empty result set', function () {
        $result = $this->db->query("SELECT * FROM iterator_test WHERE id > 100");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        expect($iterator)->toBeInstanceOf(LibSQLIterator::class);
    });

    test('iterator with numeric index mode', function () {
        $result = $this->db->query("SELECT id, value FROM iterator_test ORDER BY id LIMIT 2");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        $current = $iterator->current();

        expect($current[0])->toBe(1)
            ->and($current[1])->toBe('alpha');
    });

    test('iterator maintains correct state across operations', function () {
        $result = $this->db->query("SELECT * FROM iterator_test ORDER BY id");
        $iterator = $result->fetchArray(LibSQL::LIBSQL_LAZY);

        $iterator->rewind();
        expect($iterator->current()['id'])->toBe(1);

        $iterator->next();
        expect($iterator->current()['id'])->toBe(2);

        $iterator->rewind();
        expect($iterator->current()['id'])->toBe(1);
    });
})->group('LibSQLIteratorTest', 'Feature');
