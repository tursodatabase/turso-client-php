<?php

use Tests\TestCase;

uses(TestCase::class);

describe('LibSQL Core Methods', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE core_test (
            id INTEGER PRIMARY KEY,
            data TEXT
        )");
    });

    test('changes returns rows affected by last operation', function () {
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (1, 'test')");
        expect($this->db->changes())->toBe(1);

        $this->db->execute("UPDATE core_test SET data = 'updated' WHERE id = 1");
        expect($this->db->changes())->toBe(1);

        $this->db->execute("DELETE FROM core_test WHERE id = 1");
        expect($this->db->changes())->toBe(1);
    });

    test('changes returns zero for non-modifying operations', function () {
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (1, 'test')");
        $this->db->execute("DELETE FROM core_test WHERE id = 1");

        $this->db->execute("SELECT * FROM core_test");
        expect($this->db->changes())->toBe(0);
    });

    test('totalChanges returns cumulative changes', function () {
        $initialTotal = $this->db->totalChanges();

        $this->db->execute("INSERT INTO core_test (id, data) VALUES (1, 'first')");
        expect($this->db->totalChanges())->toBe($initialTotal + 1);

        $this->db->execute("INSERT INTO core_test (id, data) VALUES (2, 'second')");
        expect($this->db->totalChanges())->toBe($initialTotal + 2);

        $this->db->execute("UPDATE core_test SET data = 'updated' WHERE id = 1");
        expect($this->db->totalChanges())->toBe($initialTotal + 3);
    });

    test('totalChanges is cumulative across operations', function () {
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (1, 'a')");
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (2, 'b')");
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (3, 'c')");

        $totalAfterInserts = $this->db->totalChanges();
        expect($totalAfterInserts)->toBe(3);

        $this->db->execute("DELETE FROM core_test WHERE id = 1");
        expect($this->db->totalChanges())->toBe($totalAfterInserts + 1);
    });

    test('checkConnectivity returns boolean', function () {
        $isConnected = $this->db->checkConnectivity();

        expect($isConnected)->toBeBool();
    });

    test('isOnline returns boolean', function () {
        $isOnline = $this->db->isOnline();

        expect($isOnline)->toBeBool();
    });

    test('getPendingOperationsCount returns integer', function () {
        $pending = $this->db->getPendingOperationsCount();

        expect($pending)->toBeInt()
            ->and($pending)->toBeGreaterThanOrEqual(0);
    });

    test('captureIt returns boolean for event logging', function () {
        $result = $this->db->captureIt('test_event', 'SELECT 1', 'Test event captured');

        expect($result)->toBeBool();
    });

    test('captureIt with minimal parameters', function () {
        $result = $this->db->captureIt('minimal_event');

        expect($result)->toBeBool();
    });

    test('mode property is accessible', function () {
        expect($this->db->mode)->toBeString();
    });

    test('version is static method returns string', function () {
        $version = LibSQL::version();

        expect($version)->toBeString();
    });

    test('lastInsertedId returns correct value after insert', function () {
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (10, 'test')");

        expect($this->db->lastInsertedId())->toBe(10);

        $this->db->execute("INSERT INTO core_test (id, data) VALUES (25, 'test2')");

        expect($this->db->lastInsertedId())->toBe(25);
    });

    test('isAutocommit returns true by default', function () {
        expect($this->db->isAutocommit())->toBeTrue();
    });

    test('changes after batch execute', function () {
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (1, 'a')");
        $this->db->execute("INSERT INTO core_test (id, data) VALUES (2, 'b')");

        $batchSql = "INSERT INTO core_test (id, data) VALUES (3, 'c'); INSERT INTO core_test (id, data) VALUES (4, 'd');";
        $this->db->executeBatch($batchSql);

        expect($this->db->changes())->toBeGreaterThanOrEqual(1);
    });
})->group('LibSQLCoreMethodsTest', 'Feature');
