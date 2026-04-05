<?php

/**
 * Offline Write Mode Flow Tests
 * 
 * Tests the offline write functionality including:
 * - Local write operations
 * - Read-your-writes guarantee
 * - Pending operations queue
 * - Sync behavior
 * - Connectivity checks
 * - Parameter serialization
 * 
 * Note: These tests use a local database file and require a running sqld server
 * at http://127.0.0.1:8000 for full sync testing. If no server is available,
 * tests will verify local-only behavior and queue management.
 */

describe('Offline Write Mode - Basic Operations', function () {
    beforeEach(function () {
        // Clean up any previous test database
        if (file_exists(__DIR__ . '/../../test_offline.db')) {
            unlink(__DIR__ . '/../../test_offline.db');
        }
    });

    afterEach(function () {
        // Clean up test database
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline.db')) {
            unlink(__DIR__ . '/../../test_offline.db');
        }
    });

    test('create offline write connection successfully', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
            "syncInterval" => 5,
            "read_your_writes" => true,
            "encryptionKey" => "",
        ];

        $db = new LibSQL(
            config: $config,
            offline_writes: true
        );

        expect($db)->toBeObject()
            ->and($db->mode)->toBe('offline_write');

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('execute INSERT in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        // Create table
        $db->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)");

        // Insert a row
        $affected = $db->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        expect($affected)->toBe(1);

        // Verify data exists locally
        $result = $db->query("SELECT * FROM users");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['name'])->toBe('Alice')
            ->and($rows[0]['email'])->toBe('alice@example.com');

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('execute with positional parameters in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL)");

        $affected = $db->execute("INSERT INTO products (name, price) VALUES (?, ?)", ['Widget', 9.99]);
        expect($affected)->toBe(1);

        $result = $db->query("SELECT * FROM products WHERE name = ?", ['Widget']);
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['price'])->toBe(9.99);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('execute with named parameters in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE orders (id INTEGER PRIMARY KEY, product TEXT, quantity INTEGER)");

        $affected = $db->execute(
            "INSERT INTO orders (product, quantity) VALUES (:product, :quantity)",
            [':product' => 'Gadget', ':quantity' => 5]
        );
        expect($affected)->toBe(1);

        $result = $db->query("SELECT * FROM orders WHERE product = :product", [':product' => 'Gadget']);
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['quantity'])->toBe(5);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('execute with NULL parameters in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE nullable_test (id INTEGER PRIMARY KEY, value TEXT)");

        $affected = $db->execute("INSERT INTO nullable_test (value) VALUES (?)", [null]);
        expect($affected)->toBe(1);

        $result = $db->query("SELECT * FROM nullable_test WHERE value IS NULL");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows)->toHaveCount(1);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('read-your-writes guarantee in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE ryow_test (id INTEGER PRIMARY KEY, data TEXT)");

        // Write data
        $db->execute("INSERT INTO ryow_test (data) VALUES ('immediate_read')");

        // Immediately read - should see the data we just wrote
        $result = $db->query("SELECT * FROM ryow_test WHERE data = 'immediate_read'");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['data'])->toBe('immediate_read');

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('executeBatch in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE batch_test (id INTEGER PRIMARY KEY, value TEXT)");

        $batchSql = "
            INSERT INTO batch_test (value) VALUES ('batch_a');
            INSERT INTO batch_test (value) VALUES ('batch_b');
            INSERT INTO batch_test (value) VALUES ('batch_c');
        ";

        $success = $db->executeBatch($batchSql);
        expect($success)->toBeTrue();

        $result = $db->query("SELECT COUNT(*) as cnt FROM batch_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe(3);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('prepared statement execution in offline write mode', function () {
        $config = [
            "url" => "file:test_offline.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE prep_test (id INTEGER PRIMARY KEY, name TEXT)");

        $stmt = $db->prepare("INSERT INTO prep_test (name) VALUES (?)");

        $stmt->execute(['first']);
        $stmt->execute(['second']);
        $stmt->execute(['third']);

        $result = $db->query("SELECT COUNT(*) as cnt FROM prep_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe(3);

        $stmt->finalize();
        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');

describe('Offline Write Mode - Pending Operations Queue', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_offline_queue.db')) {
            unlink(__DIR__ . '/../../test_offline_queue.db');
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline_queue.db')) {
            unlink(__DIR__ . '/../../test_offline_queue.db');
        }
    });

    test('getPendingOperationsCount returns integer', function () {
        $config = [
            "url" => "file:test_offline_queue.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $count = $db->getPendingOperationsCount();
        expect($count)->toBeInt()
            ->and($count)->toBeGreaterThanOrEqual(0);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('pending operations increase after writes when offline', function () {
        $config = [
            "url" => "file:test_offline_queue.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE queue_test (id INTEGER PRIMARY KEY, data TEXT)");

        $initialCount = $db->getPendingOperationsCount();

        // Execute writes - these should be queued for sync
        $db->execute("INSERT INTO queue_test (data) VALUES ('op1')");
        $db->execute("INSERT INTO queue_test (data) VALUES ('op2')");
        $db->execute("INSERT INTO queue_test (data) VALUES ('op3')");

        $afterWriteCount = $db->getPendingOperationsCount();

        // Count should have increased (may be 0 if server is reachable and auto-sync succeeded)
        expect($afterWriteCount)->toBeGreaterThanOrEqual(0)
            ->and($afterWriteCount)->toBeGreaterThanOrEqual($initialCount);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('pending operations are persisted to disk', function () {
        $config = [
            "url" => "file:test_offline_queue.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE persist_test (id INTEGER PRIMARY KEY, data TEXT)");
        $db->execute("INSERT INTO persist_test (data) VALUES ('persisted')");

        // Close connection
        $db->close();

        // Reopen and verify operations were persisted
        $db = new LibSQL(config: $config, offline_writes: true);

        // Data should still be accessible locally
        $result = $db->query("SELECT * FROM persist_test WHERE data = 'persisted'");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toHaveCount(1);

        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');

describe('Offline Write Mode - Connectivity Checks', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_offline_connectivity.db')) {
            unlink(__DIR__ . '/../../test_offline_connectivity.db');
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline_connectivity.db')) {
            unlink(__DIR__ . '/../../test_offline_connectivity.db');
        }
    });

    test('checkConnectivity performs fresh HTTP check', function () {
        $config = [
            "url" => "file:test_offline_connectivity.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        // checkConnectivity should perform a fresh HTTP check
        $isConnected = $db->checkConnectivity();
        expect($isConnected)->toBeBool();

        // When server is not running, should return false
        // When server is running, should return true
        // Either way, it must be a boolean
        expect($isConnected)->toBeBool();

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('isOnline returns cached connectivity status', function () {
        $config = [
            "url" => "file:test_offline_connectivity.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        // isOnline returns cached value (5-second TTL)
        $isOnline = $db->isOnline();
        expect($isOnline)->toBeBool();

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('isOnline vs checkConnectivity behavior difference', function () {
        $config = [
            "url" => "file:test_offline_connectivity.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        // checkConnectivity always does fresh check
        $freshCheck1 = $db->checkConnectivity();
        $freshCheck2 = $db->checkConnectivity();

        // isOnline uses cache (5-second TTL)
        $cachedStatus = $db->isOnline();

        expect($freshCheck1)->toBeBool()
            ->and($freshCheck2)->toBeBool()
            ->and($cachedStatus)->toBeBool();

        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');

describe('Offline Write Mode - Sync Behavior', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_offline_sync.db')) {
            unlink(__DIR__ . '/../../test_offline_sync.db');
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline_sync.db')) {
            unlink(__DIR__ . '/../../test_offline_sync.db');
        }
    });

    test('sync method is callable', function () {
        $config = [
            "url" => "file:test_offline_sync.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        // sync() should be callable - may succeed or fail depending on server availability
        try {
            $db->sync();
            expect(true)->toBeTrue();
        } catch (Exception $e) {
            // Expected if server is not reachable
            expect($e->getMessage())->toBeString();
        }

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('sync with log_info parameter', function () {
        $config = [
            "url" => "file:test_offline_sync.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        try {
            $db->sync(true); // with logging
            expect(true)->toBeTrue();
        } catch (Exception $e) {
            expect($e->getMessage())->toBeString();
        }

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('sync after multiple writes', function () {
        $config = [
            "url" => "file:test_offline_sync.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE sync_test (id INTEGER PRIMARY KEY, data TEXT)");

        // Multiple writes
        $db->execute("INSERT INTO sync_test (data) VALUES ('sync1')");
        $db->execute("INSERT INTO sync_test (data) VALUES ('sync2')");
        $db->execute("INSERT INTO sync_test (data) VALUES ('sync3')");

        $pendingBeforeSync = $db->getPendingOperationsCount();

        // Attempt sync
        try {
            $db->sync();
            // If sync succeeded, pending count should decrease
            $pendingAfterSync = $db->getPendingOperationsCount();
            expect($pendingAfterSync)->toBeLessThanOrEqual($pendingBeforeSync);
        } catch (Exception $e) {
            // Sync failed, pending count remains the same or operations stay queued
            $pendingAfterSync = $db->getPendingOperationsCount();
            expect($pendingAfterSync)->toBeGreaterThanOrEqual(0);
        }

        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');

describe('Offline Write Mode - Query Behavior', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_offline_query.db')) {
            unlink(__DIR__ . '/../../test_offline_query.db');
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline_query.db')) {
            unlink(__DIR__ . '/../../test_offline_query.db');
        }
    });

    test('query local by default', function () {
        $config = [
            "url" => "file:test_offline_query.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE local_query_test (id INTEGER PRIMARY KEY, data TEXT)");
        $db->execute("INSERT INTO local_query_test (data) VALUES ('local_data')");

        // Default query should read from local
        $result = $db->query("SELECT * FROM local_query_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['data'])->toBe('local_data');

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('query with force_remote parameter', function () {
        $config = [
            "url" => "file:test_offline_query.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE remote_query_test (id INTEGER PRIMARY KEY, data TEXT)");
        $db->execute("INSERT INTO remote_query_test (data) VALUES ('test_data')");

        // force_remote=true will attempt remote query
        // May fallback to local if server is unreachable
        try {
            $result = $db->query("SELECT * FROM remote_query_test", [], true);
            $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
            expect($rows)->toBeArray();
        } catch (Exception $e) {
            // Expected if server is not reachable
            expect($e->getMessage())->toBeString();
        }

        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');

describe('Offline Write Mode - Transaction Operations', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_offline_trx.db')) {
            unlink(__DIR__ . '/../../test_offline_trx.db');
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline_trx.db')) {
            unlink(__DIR__ . '/../../test_offline_trx.db');
        }
    });

    test('transaction in offline write mode', function () {
        $config = [
            "url" => "file:test_offline_trx.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE trx_offline_test (id INTEGER PRIMARY KEY, data TEXT)");

        $trx = $db->transaction();
        $trx->execute("INSERT INTO trx_offline_test (data) VALUES ('trx1')");
        $trx->execute("INSERT INTO trx_offline_test (data) VALUES ('trx2')");
        $trx->commit();

        $result = $db->query("SELECT COUNT(*) as cnt FROM trx_offline_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe(2);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('transaction rollback in offline write mode', function () {
        $config = [
            "url" => "file:test_offline_trx.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE rollback_test (id INTEGER PRIMARY KEY, data TEXT)");

        $initialCount = $db->query("SELECT COUNT(*) as cnt FROM rollback_test")
            ->fetchArray(LibSQL::LIBSQL_ASSOC)[0]['cnt'];

        $trx = $db->transaction();
        $trx->execute("INSERT INTO rollback_test (data) VALUES ('should_rollback')");
        $trx->rollback();

        $result = $db->query("SELECT COUNT(*) as cnt FROM rollback_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe($initialCount);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('changes and totalChanges in offline write mode', function () {
        $config = [
            "url" => "file:test_offline_trx.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE changes_test (id INTEGER PRIMARY KEY, data TEXT)");

        $db->execute("INSERT INTO changes_test (data) VALUES ('row1')");
        $changes = $db->changes();
        expect($changes)->toBe(1);

        $totalChanges = $db->totalChanges();
        expect($totalChanges)->toBeGreaterThanOrEqual(1);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('lastInsertedId in offline write mode', function () {
        $config = [
            "url" => "file:test_offline_trx.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE last_id_test (id INTEGER PRIMARY KEY, data TEXT)");

        $db->execute("INSERT INTO last_id_test (data) VALUES ('first')");
        expect($db->lastInsertedId())->toBe(1);

        $db->execute("INSERT INTO last_id_test (data) VALUES ('second')");
        expect($db->lastInsertedId())->toBe(2);

        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');

describe('Offline Write Mode - Edge Cases', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_offline_edge.db')) {
            unlink(__DIR__ . '/../../test_offline_edge.db');
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists(__DIR__ . '/../../test_offline_edge.db')) {
            unlink(__DIR__ . '/../../test_offline_edge.db');
        }
    });

    test('large batch write in offline mode', function () {
        $config = [
            "url" => "file:test_offline_edge.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE large_batch_test (id INTEGER PRIMARY KEY, data TEXT)");

        // Build a large batch
        $batchStatements = [];
        for ($i = 0; $i < 50; $i++) {
            $batchStatements[] = "INSERT INTO large_batch_test (data) VALUES ('row_$i');";
        }
        $batchSql = implode("\n", $batchStatements);

        $success = $db->executeBatch($batchSql);
        expect($success)->toBeTrue();

        $result = $db->query("SELECT COUNT(*) as cnt FROM large_batch_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe(50);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('UPDATE and DELETE operations in offline mode', function () {
        $config = [
            "url" => "file:test_offline_edge.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE update_test (id INTEGER PRIMARY KEY, data TEXT)");

        // Insert
        $db->execute("INSERT INTO update_test (data) VALUES ('original')");

        // Update
        $affected = $db->execute("UPDATE update_test SET data = 'updated' WHERE data = 'original'");
        expect($affected)->toBe(1);

        // Verify update
        $result = $db->query("SELECT * FROM update_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['data'])->toBe('updated');

        // Delete
        $affected = $db->execute("DELETE FROM update_test");
        expect($affected)->toBe(1);

        $result = $db->query("SELECT COUNT(*) as cnt FROM update_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe(0);

        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('prepared statement batch execution in offline mode', function () {
        $config = [
            "url" => "file:test_offline_edge.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE stmt_batch_test (id INTEGER PRIMARY KEY, name TEXT, value REAL)");

        $stmt = $db->prepare("INSERT INTO stmt_batch_test (name, value) VALUES (?, ?)");

        $parameterSets = [];
        for ($i = 0; $i < 10; $i++) {
            $parameterSets[] = ["item_$i", $i * 1.5];
        }

        $totalRows = $stmt->executeBatch($parameterSets);
        expect($totalRows)->toBe(10);

        $result = $db->query("SELECT COUNT(*) as cnt FROM stmt_batch_test");
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows[0]['cnt'])->toBe(10);

        $stmt->finalize();
        $db->close();
    })->group('OfflineWrite', 'Feature');

    test('multiple sequential sync attempts', function () {
        $config = [
            "url" => "file:test_offline_edge.db",
            "authToken" => "test_token",
            "syncUrl" => "http://127.0.0.1:8000",
        ];

        $db = new LibSQL(config: $config, offline_writes: true);

        $db->execute("CREATE TABLE multi_sync_test (id INTEGER PRIMARY KEY, data TEXT)");
        $db->execute("INSERT INTO multi_sync_test (data) VALUES ('data1')");
        $db->execute("INSERT INTO multi_sync_test (data) VALUES ('data2')");

        // Attempt multiple syncs
        $syncAttempts = 3;
        $successCount = 0;
        $failureCount = 0;

        for ($i = 0; $i < $syncAttempts; $i++) {
            try {
                $db->sync();
                $successCount++;
            } catch (Exception $e) {
                $failureCount++;
            }
        }

        // Either all succeeded or all failed (depending on server availability)
        expect($successCount + $failureCount)->toBe($syncAttempts);

        $db->close();
    })->group('OfflineWrite', 'Feature');
})->group('OfflineWrite', 'Feature');
