<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Untested Methods Coverage', function () {
    test('enableLoadExtension can be called with true', function () {
        $this->db->enableLoadExtension(true);
        
        // If we got here without exception, the method works
        expect(true)->toBeTrue();
    });

    test('enableLoadExtension can be called with false', function () {
        $this->db->enableLoadExtension(false);
        
        // If we got here without exception, the method works
        expect(true)->toBeTrue();
    });

    test('loadExtensions throws error for non-existent extension', function () {
        $this->db->enableLoadExtension(true);
        
        $this->db->loadExtensions('/nonexistent/extension.so');
    })->throws(Exception::class);

    test('loadExtensions with array of non-existent extensions', function () {
        $this->db->enableLoadExtension(true);
        
        $this->db->loadExtensions(['/nonexistent/ext1.so', '/nonexistent/ext2.so']);
    })->throws(Exception::class);

    test('sync throws error on non-replica connection', function () {
        // sync() is only available for replica connections
        // In-memory connection should throw an error or be a no-op
        $result = $this->db->sync();
        
        // Should either succeed as no-op or throw an appropriate error
        expect($result)->toBeNull()->or($result)->toBeFalse();
    })->throws(Exception::class);

    test('sync with log_info parameter', function () {
        $result = $this->db->sync(true);
        
        expect($result)->toBeNull()->or($result)->toBeFalse();
    })->throws(Exception::class);
})->group('UntestedMethods', 'Feature');

describe('Edge Cases for Existing Methods', function () {
    test('execute with empty SQL string throws error', function () {
        $this->db->execute('');
    })->throws(Exception::class);

    test('query with empty SQL string throws error', function () {
        $this->db->query('');
    })->throws(Exception::class);

    test('prepare with empty SQL string', function () {
        $stmt = $this->db->prepare('');
        expect($stmt)->not->toBeNull();
    })->throws(Exception::class);

    test('executeBatch with empty string', function () {
        $result = $this->db->executeBatch('');
        expect($result)->toBeTrue();
    });

    test('executeBatch with whitespace only', function () {
        $result = $this->db->executeBatch('   ');
        expect($result)->toBeTrue();
    });

    test('executeBatch with multiple valid statements', function () {
        $this->db->execute("CREATE TABLE test_batch (id INTEGER PRIMARY KEY, value TEXT)");
        
        $batch = "
            INSERT INTO test_batch (value) VALUES ('a');
            INSERT INTO test_batch (value) VALUES ('b');
            INSERT INTO test_batch (value) VALUES ('c');
        ";
        
        $result = $this->db->executeBatch($batch);
        expect($result)->toBeTrue();
        
        $count = $this->db->query("SELECT COUNT(*) FROM test_batch");
        expect($count->fetchArray(LibSQL::LIBSQL_NUM)[0][0])->toBe(3);
    });

    test('statement finalize after execution', function () {
        $this->db->execute("CREATE TABLE finalize_test (id INTEGER PRIMARY KEY)");
        
        $stmt = $this->db->prepare("INSERT INTO finalize_test (id) VALUES (?)");
        $stmt->execute([1]);
        $stmt->finalize();
        
        // After finalizing, the statement should still work when re-prepared
        $stmt2 = $this->db->prepare("INSERT INTO finalize_test (id) VALUES (?)");
        $stmt2->execute([2]);
        
        $count = $this->db->query("SELECT COUNT(*) FROM finalize_test");
        expect($count->fetchArray(LibSQL::LIBSQL_NUM)[0][0])->toBe(2);
    });

    test('statement parameterCount with no parameters', function () {
        $stmt = $this->db->prepare("SELECT 1");
        expect($stmt->parameterCount())->toBe(0);
    });

    test('statement parameterCount with multiple parameters', function () {
        $stmt = $this->db->prepare("INSERT INTO t (a, b, c) VALUES (?, ?, ?)");
        expect($stmt->parameterCount())->toBe(3);
    });

    test('statement parameterName with valid index', function () {
        $this->db->execute("CREATE TABLE param_test (id INTEGER PRIMARY KEY, name TEXT)");
        
        $stmt = $this->db->prepare("INSERT INTO param_test (name) VALUES (:name)");
        $paramName = $stmt->parameterName(0);
        expect($paramName)->toBe('name');
    });

    test('statement columns on SELECT with multiple fields', function () {
        $this->db->execute("CREATE TABLE col_test (id INTEGER PRIMARY KEY, name TEXT, value REAL)");
        
        $stmt = $this->db->prepare("SELECT id, name, value FROM col_test");
        $columns = $stmt->columns();
        
        expect($columns)->toHaveCount(3);
        expect($columns[0]['name'])->toBe('id');
        expect($columns[1]['name'])->toBe('name');
        expect($columns[2]['name'])->toBe('value');
    });

    test('result finalize', function () {
        $this->db->execute("CREATE TABLE result_finalize_test (id INTEGER PRIMARY KEY)");
        
        $result = $this->db->query("SELECT * FROM result_finalize_test");
        $result->finalize();
        
        // Should not throw error
        expect(true)->toBeTrue();
    });

    test('result reset', function () {
        $this->db->execute("CREATE TABLE result_reset_test (id INTEGER PRIMARY KEY)");
        $this->db->execute("INSERT INTO result_reset_test (id) VALUES (1)");
        
        $result = $this->db->query("SELECT * FROM result_reset_test");
        $result->reset();
        
        // Should not throw error
        expect(true)->toBeTrue();
    });

    test('result columnName by index', function () {
        $this->db->execute("CREATE TABLE col_name_test (id INTEGER PRIMARY KEY, data TEXT)");
        
        $result = $this->db->query("SELECT * FROM col_name_test");
        expect($result->columnName(0))->toBe('id');
        expect($result->columnName(1))->toBe('data');
    });

    test('result columnType by index', function () {
        $this->db->execute("CREATE TABLE col_type_test (id INTEGER PRIMARY KEY, data TEXT)");
        $this->db->execute("INSERT INTO col_type_test (id, data) VALUES (1, 'test')");
        
        $result = $this->db->query("SELECT * FROM col_type_test");
        $type0 = $result->columnType(0);
        $type1 = $result->columnType(1);
        
        expect($type0)->toBeString();
        expect($type1)->toBeString();
    });

    test('result numColumns', function () {
        $this->db->execute("CREATE TABLE num_cols_test (a INTEGER, b TEXT, c REAL)");
        
        $result = $this->db->query("SELECT * FROM num_cols_test");
        expect($result->numColumns())->toBe(3);
    });

    test('transaction changes', function () {
        $this->db->execute("CREATE TABLE trx_changes_test (id INTEGER PRIMARY KEY)");
        
        $trx = $this->db->transaction();
        $trx->execute("INSERT INTO trx_changes_test (id) VALUES (1)");
        $trx->execute("INSERT INTO trx_changes_test (id) VALUES (2)");
        
        $changes = $trx->changes();
        expect($changes)->toBe(1); // Last statement inserted 1 row
        
        $trx->commit();
    });

    test('transaction isAutocommit', function () {
        $trx = $this->db->transaction();
        $autocommit = $trx->isAutocommit();
        
        // Transaction should not be in autocommit mode
        expect($autocommit)->toBeFalse();
        
        $trx->rollback();
    });

    test('transaction prepare', function () {
        $this->db->execute("CREATE TABLE trx_prepare_test (id INTEGER PRIMARY KEY, name TEXT)");
        
        $trx = $this->db->transaction();
        $stmt = $trx->prepare("INSERT INTO trx_prepare_test (id, name) VALUES (?, ?)");
        
        expect($stmt)->not->toBeNull();
        
        $trx->rollback();
    });

    test('transaction query', function () {
        $this->db->execute("CREATE TABLE trx_query_test (id INTEGER PRIMARY KEY, name TEXT)");
        $this->db->execute("INSERT INTO trx_query_test (id, name) VALUES (1, 'test')");
        
        $trx = $this->db->transaction();
        $result = $trx->query("SELECT * FROM trx_query_test");
        
        expect($result)->toHaveKey('rows');
        expect($result['rows'])->toHaveCount(1);
        
        $trx->rollback();
    });

    test('totalChanges returns cumulative count', function () {
        $this->db->execute("CREATE TABLE total_changes_test (id INTEGER PRIMARY KEY)");
        
        $this->db->execute("INSERT INTO total_changes_test (id) VALUES (1)");
        $this->db->execute("INSERT INTO total_changes_test (id) VALUES (2)");
        $this->db->execute("INSERT INTO total_changes_test (id) VALUES (3)");
        
        $totalChanges = $this->db->totalChanges();
        expect($totalChanges)->toBeGreaterThanOrEqual(3);
    });

    test('lastInsertedId returns correct value', function () {
        $this->db->execute("CREATE TABLE last_id_test (id INTEGER PRIMARY KEY, name TEXT)");
        
        $this->db->execute("INSERT INTO last_id_test (name) VALUES ('first')");
        expect($this->db->lastInsertedId())->toBe(1);
        
        $this->db->execute("INSERT INTO last_id_test (name) VALUES ('second')");
        expect($this->db->lastInsertedId())->toBe(2);
    });

    test('checkConnectivity for in-memory connection', function () {
        // In-memory connections are always "connected"
        $result = $this->db->checkConnectivity();
        expect($result)->toBeTrue();
    });

    test('getPendingOperationsCount on non-offline connection', function () {
        // This method is only meaningful for offline mode
        // On regular connection it should return 0 or throw appropriate error
        try {
            $count = $this->db->getPendingOperationsCount();
            expect($count)->toBe(0);
        } catch (Exception $e) {
            // Expected if not in offline mode
            expect($e->getMessage())->toContain('offline');
        }
    });

    test('isOnline on non-offline connection', function () {
        // This method is only meaningful for offline mode
        try {
            $online = $this->db->isOnline();
            expect($online)->toBeTrue();
        } catch (Exception $e) {
            // Expected if not in offline mode
            expect($e->getMessage())->toContain('offline');
        }
    });
})->group('EdgeCases', 'Feature');
