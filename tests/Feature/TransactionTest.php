<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Transactions', function () {
    beforeEach(function () {
        $this->db->execute("CREATE TABLE accounts (
            id INTEGER PRIMARY KEY,
            balance INTEGER
        )");
    });

    test('atomic transaction commit', function () {
        $trx = $this->db->transaction();
        
        $trx->execute("INSERT INTO accounts (balance) VALUES (1000)");
        $trx->execute("INSERT INTO accounts (balance) VALUES (2000)");
        
        expect($trx->isAutocommit())->toBeFalse();
        $trx->commit();

        $result = $this->db->query("SELECT SUM(balance) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(3000);
    });

    test('transaction rollback', function () {
        $trx = $this->db->transaction();
        
        $trx->execute("INSERT INTO accounts (balance) VALUES (500)");
        $trx->rollback();

        $result = $this->db->query("SELECT COUNT(*) FROM accounts");
        expect($result->fetchSingle(LibSQL::LIBSQL_NUM)[0])->toBe(0);
    });
})->group('TransactionTest', 'Feature');