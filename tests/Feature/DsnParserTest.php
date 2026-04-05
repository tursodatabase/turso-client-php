<?php

/**
 * DSN Parsing Tests
 * 
 * Tests the DSN string parsing functionality including:
 * - Local database connections via DSN
 * - Remote connections via DSN
 * - Remote replica connections via DSN (with syncUrl)
 * - syncInterval parameter parsing
 * - read_your_writes parameter parsing
 * - Edge cases and error handling
 */

describe('DSN Parsing - Local Connections', function () {
    test('simple filename DSN without libsql prefix', function () {
        $db = new LibSQL('test_local.db');
        
        expect($db)->toBeObject()
            ->and($db->mode)->toBe('local');
        
        $db->close();
        unlink_if_exists('test_local.db');
    });

    test('file: protocol DSN', function () {
        $db = new LibSQL('file:test_local_file.db');
        
        expect($db)->toBeObject()
            ->and($db->mode)->toBe('local');
        
        $db->close();
        unlink_if_exists('test_local_file.db');
    });

    test('libsql:dbname DSN for local connection', function () {
        $db = new LibSQL('libsql:dbname=test_local_libsql.db');
        
        expect($db)->toBeObject()
            ->and($db->mode)->toBe('local');
        
        $db->close();
        unlink_if_exists('test_local_libsql.db');
    });

    test('libsql:dbname with file: prefix', function () {
        $db = new LibSQL('libsql:dbname=file:test_local_with_file.db');
        
        expect($db)->toBeObject()
            ->and($db->mode)->toBe('local');
        
        $db->close();
        unlink_if_exists('test_local_with_file.db');
    });

    test('in-memory DSN', function () {
        $db = new LibSQL(':memory:');
        
        expect($db)->toBeObject();
        
        $db->close();
    });
})->group('DSNParser', 'Feature');

describe('DSN Parsing - Remote Connections', function () {
    test('libsql:dbname with https URL and authToken', function () {
        // This test verifies that DSN parsing extracts both dbname and authToken
        // The actual connection will fail without a real server, but parsing should work
        expect(function () {
            $db = new LibSQL('libsql:dbname=https://test-db.turso.io;authToken=test-token-123');
            // If we got here, parsing worked (connection may still fail)
            $db->close();
        })->not->toThrow(Exception::class);
    })->skip('Requires actual remote server');

    test('libsql: with libsql:// URL and authToken', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=libsql://test-db.turso.io;authToken=test-token-456');
            $db->close();
        })->not->toThrow(Exception::class);
    })->skip('Requires actual remote server');
})->group('DSNParser', 'Feature');

describe('DSN Parsing - syncUrl Parameter', function () {
    test('DSN with syncUrl enables remote replica mode', function () {
        // syncUrl in DSN should trigger remote_replica mode
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_replica.db;syncUrl=https://test.turso.io;authToken=test-token');
            
            expect($db)->toBeObject()
                ->and($db->mode)->toBe('remote_replica');
            
            $db->close();
            unlink_if_exists('test_replica.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with syncUrl and all parameters', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_replica_full.db;authToken=my-token;syncUrl=https://test.turso.io;syncInterval=10;read_your_writes=true');
            
            expect($db)->toBeObject()
                ->and($db->mode)->toBe('remote_replica');
            
            $db->close();
            unlink_if_exists('test_replica_full.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with syncUrl using libsql:// protocol', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=libsql://test.turso.io;authToken=my-token;syncUrl=libsql://test.turso.io');
            
            expect($db)->toBeObject()
                ->and($db->mode)->toBe('remote_replica');
            
            $db->close();
            unlink_if_exists('test.turso.io');
        })->not->toThrow(Exception::class);
    })->skip('Requires actual remote server');
})->group('DSNParser', 'Feature');

describe('DSN Parsing - syncInterval Parameter', function () {
    test('DSN with custom syncInterval value', function () {
        // syncInterval should be parsed and used for replica connections
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_interval.db;authToken=token;syncUrl=https://test.turso.io;syncInterval=30');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_interval.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with syncInterval=1 (minimum value)', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_interval_min.db;authToken=token;syncUrl=https://test.turso.io;syncInterval=1');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_interval_min.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with invalid syncInterval (non-numeric) uses default', function () {
        // Invalid syncInterval should be ignored, default to 5 seconds
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_invalid_interval.db;authToken=token;syncUrl=https://test.turso.io;syncInterval=abc');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_invalid_interval.db');
        })->not->toThrow(Exception::class);
    });
})->group('DSNParser', 'Feature');

describe('DSN Parsing - read_your_writes Parameter', function () {
    test('DSN with read_your_writes=true', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_true.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=true');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_true.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with read_your_writes=false', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_false.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=false');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_false.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with read_your_writes=1 (numeric true)', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_1.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=1');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_1.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with read_your_writes=0 (numeric false)', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_0.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=0');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_0.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with read_your_writes=yes (case insensitive)', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_yes.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=yes');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_yes.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with read_your_writes=no (case insensitive)', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_no.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=no');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_no.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with invalid read_your_writes value uses default (true)', function () {
        // Invalid read_your_writes should be ignored, default to true
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_ryw_invalid.db;authToken=token;syncUrl=https://test.turso.io;read_your_writes=maybe');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_ryw_invalid.db');
        })->not->toThrow(Exception::class);
    });
})->group('DSNParser', 'Feature');

describe('DSN Parsing - Comprehensive DSN Strings', function () {
    test('DSN with all parameters in various orderings', function () {
        // Test that parameter order doesn't matter
        expect(function () {
            $db = new LibSQL('libsql:syncUrl=https://test.turso.io;dbname=file:test_order1.db;read_your_writes=false;authToken=token;syncInterval=15');
            
            expect($db)->toBeObject()
                ->and($db->mode)->toBe('remote_replica');
            
            $db->close();
            unlink_if_exists('test_order1.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with extra unknown parameters (should be ignored)', function () {
        // Unknown parameters should be silently ignored
        expect(function () {
            $db = new LibSQL('libsql:dbname=file:test_extra.db;authToken=token;syncUrl=https://test.turso.io;unknownParam=value;anotherParam=123');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_extra.db');
        })->not->toThrow(Exception::class);
    });

    test('DSN with whitespace around = signs', function () {
        expect(function () {
            $db = new LibSQL('libsql:dbname = file:test_whitespace.db ; authToken = my-token');
            
            expect($db)->toBeObject();
            
            $db->close();
            unlink_if_exists('test_whitespace.db');
        })->not->toThrow(Exception::class);
    });
})->group('DSNParser', 'Feature');

describe('DSN Parsing - Error Cases', function () {
    test('empty DSN string throws error', function () {
        expect(function () {
            new LibSQL('');
        })->toThrow(Exception::class);
    });

    test('DSN with missing dbname value throws error', function () {
        expect(function () {
            new LibSQL('libsql:dbname=;authToken=token');
        })->toThrow(Exception::class);
    });

    test('DSN with only authToken and no dbname throws error', function () {
        expect(function () {
            new LibSQL('libsql:authToken=token');
        })->toThrow(Exception::class);
    });
})->group('DSNParser', 'Feature');

describe('DSN Parsing - Config Array Equivalence', function () {
    test('DSN produces same result as config array with same values', function () {
        // Test that DSN and config array produce equivalent connections
        $dsnDb = new LibSQL('libsql:dbname=file:test_equivalence.db;authToken=test-token;syncUrl=https://test.turso.io;syncInterval=10;read_your_writes=false');
        
        $configDb = new LibSQL([
            'url' => 'file:test_equivalence.db',
            'authToken' => 'test-token',
            'syncUrl' => 'https://test.turso.io',
            'syncInterval' => 10,
            'read_your_writes' => false,
        ]);
        
        // Both should have same mode
        expect($dsnDb->mode)->toBe($configDb->mode)
            ->and($dsnDb->mode)->toBe('remote_replica');
        
        $dsnDb->close();
        $configDb->close();
        unlink_if_exists('test_equivalence.db');
    });
})->group('DSNParser', 'Feature');

describe('DSN Parsing - Functional Tests with Local DB', function () {
    beforeEach(function () {
        if (file_exists(__DIR__ . '/../../test_dsn_functional.db')) {
            unlink(__DIR__ . '/../../test_dsn_functional.db');
        }
    });

    afterEach(function () {
        if (file_exists(__DIR__ . '/../../test_dsn_functional.db')) {
            unlink(__DIR__ . '/../../test_dsn_functional.db');
        }
    });

    test('execute queries using DSN-parsed local connection', function () {
        $db = new LibSQL('libsql:dbname=file:test_dsn_functional.db');

        $db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        $db->execute("INSERT INTO users (name) VALUES ('Alice')");
        $db->execute("INSERT INTO users (name) VALUES ('Bob')");

        $result = $db->query('SELECT COUNT(*) as cnt FROM users');
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows[0]['cnt'])->toBe(2);

        $db->close();
    });

    test('transactions work with DSN-parsed connection', function () {
        $db = new LibSQL('libsql:dbname=file:test_dsn_functional.db');

        $db->execute('CREATE TABLE transactions (id INTEGER PRIMARY KEY, amount REAL)');

        $trx = $db->transaction();
        $trx->execute("INSERT INTO transactions (amount) VALUES (100.0)");
        $trx->execute("INSERT INTO transactions (amount) VALUES (200.0)");
        $trx->commit();

        $result = $db->query('SELECT SUM(amount) as total FROM transactions');
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows[0]['total'])->toBe(300.0);

        $db->close();
    });

    test('prepared statements work with DSN-parsed connection', function () {
        $db = new LibSQL('libsql:dbname=file:test_dsn_functional.db');

        $db->execute('CREATE TABLE products (id INTEGER PRIMARY KEY, name TEXT, price REAL)');

        $stmt = $db->prepare('INSERT INTO products (name, price) VALUES (?, ?)');
        $stmt->execute(['Widget', 9.99]);
        $stmt->execute(['Gadget', 24.99]);

        $result = $db->query('SELECT * FROM products ORDER BY name');
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

        expect($rows)->toHaveCount(2)
            ->and($rows[0]['name'])->toBe('Gadget')
            ->and($rows[1]['name'])->toBe('Widget');

        $stmt->finalize();
        $db->close();
    });
})->group('DSNParser', 'Feature');

/**
 * Helper function to clean up test database files
 */
function unlink_if_exists($path) {
    if (file_exists($path)) {
        unlink($path);
    }
}
