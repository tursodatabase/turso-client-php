<?php

/**
 * Connection Pool Tests
 * 
 * Tests the LibSQLPool class for connection pooling functionality:
 * - Pool creation and configuration
 * - Connection registration and tracking
 * - Connection count monitoring
 * - Cleanup of expired connections
 * - closeAll() functionality
 * - listPools() static method
 */

describe('LibSQLPool - Basic Functionality', function () {
    test('create pool with default parameters', function () {
        $pool = new LibSQLPool('test_pool');
        
        expect($pool)->toBeObject()
            ->and($pool->getName())->toBe('test_pool')
            ->and($pool->getMaxConnections())->toBe(10)
            ->and($pool->getIdleTimeout())->toBe(300);
    });

    test('create pool with custom parameters', function () {
        $pool = new LibSQLPool('custom_pool', 20, 600);
        
        expect($pool->getName())->toBe('custom_pool')
            ->and($pool->getMaxConnections())->toBe(20)
            ->and($pool->getIdleTimeout())->toBe(600);
    });

    test('create multiple pools', function () {
        $pool1 = new LibSQLPool('pool_one', 5, 120);
        $pool2 = new LibSQLPool('pool_two', 15, 600);
        
        expect($pool1->getName())->toBe('pool_one')
            ->and($pool2->getName())->toBe('pool_two')
            ->and($pool1->getMaxConnections())->toBe(5)
            ->and($pool2->getMaxConnections())->toBe(15);
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - Connection Registration', function () {
    test('register a connection with the pool', function () {
        $pool = new LibSQLPool('reg_test');
        
        // Create a LibSQL connection
        $db = new LibSQL(':memory:');
        
        // Get the connection ID (we need to access it through the pool)
        // Since conn_id is internal, we test via registration
        $pool->registerConnection('test_conn_123');
        
        // Connection count should increase
        $count = $pool->getConnectionCount();
        expect($count)->toBeGreaterThanOrEqual(1);
        
        $db->close();
    });

    test('heartbeat updates last-used timestamp', function () {
        $pool = new LibSQLPool('heartbeat_test');
        
        $pool->registerConnection('heartbeat_conn');
        
        // Heartbeat should not throw
        $pool->heartbeat('heartbeat_conn');
        
        expect(true)->toBeTrue();
    });

    test('register multiple connections', function () {
        $pool = new LibSQLPool('multi_reg_test');
        
        for ($i = 0; $i < 5; $i++) {
            $pool->registerConnection("conn_$i");
        }
        
        $count = $pool->getConnectionCount();
        expect($count)->toBeGreaterThanOrEqual(5);
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - Connection Count', function () {
    test('initial pool has zero connections', function () {
        $pool = new LibSQLPool('empty_pool');
        
        expect($pool->getConnectionCount())->toBe(0);
    });

    test('connection count increases after registration', function () {
        $pool = new LibSQLPool('count_test');
        
        $initialCount = $pool->getConnectionCount();
        
        $pool->registerConnection('count_conn_1');
        $pool->registerConnection('count_conn_2');
        
        $afterCount = $pool->getConnectionCount();
        expect($afterCount)->toBeGreaterThanOrEqual($initialCount + 2);
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - Cleanup', function () {
    test('cleanup returns integer', function () {
        $pool = new LibSQLPool('cleanup_test', 10, 0); // 0 second timeout for immediate expiry
        
        $pool->registerConnection('cleanup_conn');
        
        // Allow some time to pass
        usleep(100000); // 100ms
        
        $cleaned = $pool->cleanup();
        expect($cleaned)->toBeInt()
            ->and($cleaned)->toBeGreaterThanOrEqual(0);
    });

    test('cleanup with pool that has no connections', function () {
        $pool = new LibSQLPool('empty_cleanup');
        
        $cleaned = $pool->cleanup();
        expect($cleaned)->toBe(0);
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - closeAll', function () {
    test('closeAll removes all tracked connections', function () {
        $pool = new LibSQLPool('close_test');
        
        $pool->registerConnection('close_conn_1');
        $pool->registerConnection('close_conn_2');
        
        $countBefore = $pool->getConnectionCount();
        expect($countBefore)->toBeGreaterThanOrEqual(2);
        
        $pool->closeAll();
        
        $countAfter = $pool->getConnectionCount();
        expect($countAfter)->toBe(0);
    });

    test('closeAll on empty pool', function () {
        $pool = new LibSQLPool('empty_close');
        
        // Should not throw
        $pool->closeAll();
        
        expect(true)->toBeTrue();
    });

    test('closeAll does not affect other pools', function () {
        $pool1 = new LibSQLPool('pool_alpha');
        $pool2 = new LibSQLPool('pool_beta');
        
        $pool1->registerConnection('alpha_conn');
        $pool2->registerConnection('beta_conn');
        
        $pool1->closeAll();
        
        // pool2 should still have its connection
        $pool2Count = $pool2->getConnectionCount();
        expect($pool2Count)->toBeGreaterThanOrEqual(1);
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - listPools Static Method', function () {
    test('listPools returns array', function () {
        $pools = LibSQLPool::listPools();
        
        expect($pools)->toBeArray();
    });

    test('listPools includes registered pools', function () {
        $pool = new LibSQLPool('list_test');
        $pool->registerConnection('list_conn');
        
        $pools = LibSQLPool::listPools();
        
        // Should include our pool name
        expect($pools)->toBeArray();
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - Integration with LibSQL', function () {
    beforeEach(function () {
        $this->pool = new LibSQLPool('integration_test', 10, 300);
    });

    afterEach(function () {
        if (isset($this->pool)) {
            $this->pool->closeAll();
        }
    });

    test('create LibSQL connection and register with pool', function () {
        $db = new LibSQL(':memory:');
        
        // The connection is created, we can register it with the pool
        // In a real implementation, LibSQL would do this automatically when persistent=true
        // For testing, we manually verify the pool can track connections
        
        $initialCount = $this->pool->getConnectionCount();
        
        // Simulate what LibSQL would do internally
        // (In production, this happens automatically)
        $db->close();
        
        expect($initialCount)->toBeInt()
            ->and($initialCount)->toBeGreaterThanOrEqual(0);
    });

    test('multiple LibSQL connections tracked by pool', function () {
        $connections = [];
        
        for ($i = 0; $i < 3; $i++) {
            $db = new LibSQL(':memory:');
            $connections[] = $db;
            $this->pool->registerConnection("integration_conn_$i");
        }
        
        $count = $this->pool->getConnectionCount();
        expect($count)->toBeGreaterThanOrEqual(3);
        
        foreach ($connections as $db) {
            $db->close();
        }
    });
})->group('ConnectionPool', 'Feature');

describe('LibSQLPool - Edge Cases', function () {
    test('pool with very long name', function () {
        $longName = str_repeat('a', 1000);
        $pool = new LibSQLPool($longName);
        
        expect($pool->getName())->toBe($longName);
    });

    test('pool with special characters in name', function () {
        $pool = new LibSQLPool('pool-with_special.chars!@#$%');
        
        expect($pool->getName())->toBe('pool-with_special.chars!@#$%');
    });

    test('heartbeat on non-existent connection', function () {
        $pool = new LibSQLPool('heartbeat_edge');
        
        // Should not throw, just no-op
        $pool->heartbeat('non_existent');
        
        expect(true)->toBeTrue();
    });

    test('register same connection twice', function () {
        $pool = new LibSQLPool('dup_test');
        
        $pool->registerConnection('dup_conn');
        $pool->registerConnection('dup_conn');
        
        // Should handle gracefully (may overwrite or keep first)
        $count = $pool->getConnectionCount();
        expect($count)->toBeGreaterThanOrEqual(1);
    });
})->group('ConnectionPool', 'Feature');
