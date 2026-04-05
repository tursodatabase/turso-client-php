<?php

use Tests\TestCase;

uses(TestCase::class);

describe('LibSQLTransaction Extended', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE accounts (
            id INTEGER PRIMARY KEY,
            name TEXT,
            balance INTEGER
        )");

        $this->db->execute("INSERT INTO accounts (id, name, balance) VALUES (1, 'Alice', 1000)");
        $this->db->execute("INSERT INTO accounts (id, name, balance) VALUES (2, 'Bob', 2000)");
    });

    test('changes returns affected rows in transaction', function () {
        $trx = $this->db->transaction();

        $trx->execute("UPDATE accounts SET balance = 1500 WHERE id = 1");
        $changes = $trx->changes();

        expect($changes)->toBe(1);

        $trx->commit();
    });

    test('prepare creates statement within transaction', function () {
        $trx = $this->db->transaction();

        $stmt = $trx->prepare("UPDATE accounts SET balance = ? WHERE id = ?");

        $stmt->bindPositional([1500, 1]);
        $stmt->execute();

        $stmt->reset();

        $stmt->bindPositional([2500, 2]);
        $stmt->execute();

        $trx->commit();

        $result = $this->db->query("SELECT balance FROM accounts ORDER BY id");
        $rows = $result->fetchArray(LibSQL::LIBSQL_NUM);

        expect($rows[0][0])->toBe(1500)
            ->and($rows[1][0])->toBe(2500);
    });

    test('query executes within transaction context', function () {
        $trx = $this->db->transaction();

        $trx->execute("INSERT INTO accounts (id, name, balance) VALUES (3, 'Charlie', 3000)");

        $rows = $trx->query("SELECT * FROM accounts ORDER BY id");

        expect(count($rows))->toBe(3)
            ->and($rows[2]['name'])->toBe('Charlie');

        $trx->commit();
    });

    test('transaction with IMMEDIATE mode', function () {
        $trx = $this->db->transaction('IMMEDIATE');

        $trx->execute("UPDATE accounts SET balance = 500 WHERE id = 1");
        expect($trx->isAutocommit())->toBeFalse();

        $trx->commit();

        $result = $this->db->query("SELECT balance FROM accounts WHERE id = 1");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(500);
    });

    test('transaction with EXCLUSIVE mode', function () {
        $trx = $this->db->transaction('EXCLUSIVE');

        $trx->execute("DELETE FROM accounts WHERE id = 2");
        $trx->commit();

        $result = $this->db->query("SELECT COUNT(*) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(1);
    });

    test('transaction with DEFERRED mode', function () {
        $trx = $this->db->transaction('DEFERRED');

        $trx->execute("INSERT INTO accounts (id, name, balance) VALUES (3, 'Dave', 500)");
        $trx->commit();

        $result = $this->db->query("SELECT COUNT(*) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(3);
    });

    test('rollback discards all transaction changes', function () {
        $initialResult = $this->db->query("SELECT SUM(balance) FROM accounts");
        $initialSum = $initialResult->fetchSingle(LibSQL::LIBSQL_NUM)[0];

        $trx = $this->db->transaction();

        $trx->execute("UPDATE accounts SET balance = 0");
        $trx->execute("INSERT INTO accounts (id, name, balance) VALUES (99, 'Temp', 100)");

        $trx->rollback();

        $result = $this->db->query("SELECT SUM(balance) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe($initialSum);
    });

    test('multiple operations in single transaction maintain consistency', function () {
        $trx = $this->db->transaction();

        // Transfer from Alice to Bob
        $trx->execute("UPDATE accounts SET balance = balance - 200 WHERE id = 1");
        $trx->execute("UPDATE accounts SET balance = balance + 200 WHERE id = 2");

        $trx->commit();

        $result = $this->db->query("SELECT balance FROM accounts ORDER BY id");
        $rows = $result->fetchArray(LibSQL::LIBSQL_NUM);

        expect($rows[0][0])->toBe(800)
            ->and($rows[1][0])->toBe(2200);
    });

    test('prepare and query in same transaction', function () {
        $trx = $this->db->transaction();

        $stmt = $trx->prepare("INSERT INTO accounts (id, name, balance) VALUES (?, ?, ?)");
        $stmt->bindPositional([3, 'Eve', 1500]);
        $stmt->execute();

        $rows = $trx->query("SELECT * FROM accounts ORDER BY id");
        expect(count($rows))->toBe(3);

        $trx->commit();
    });
})->group('LibSQLTransactionExtendedTest', 'Feature');
