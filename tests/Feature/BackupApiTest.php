<?php

/**
 * Backup API Tests
 * 
 * Tests the backup_to_file functionality:
 * - Backup local database to file
 * - Backup in-memory database to file
 * - Backup with data integrity verification
 * - Error handling for invalid destinations
 * - Backup after modifications
 */

describe('Backup API - Basic Functionality', function () {
    beforeEach(function () {
        $this->backupFile = __DIR__ . '/../../test_backup.db';
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
    });

    test('backupToFile returns true on success', function () {
        $this->db = new LibSQL(':memory:');
        
        $result = $this->db->backupToFile($this->backupFile);
        
        expect($result)->toBeTrue()
            ->and(file_exists($this->backupFile))->toBeTrue();
    });

    test('backup creates a valid database file', function () {
        $this->db = new LibSQL(':memory:');
        $this->db->execute('CREATE TABLE test (id INTEGER PRIMARY KEY, data TEXT)');
        $this->db->execute("INSERT INTO test (data) VALUES ('backup_test')");
        
        $this->db->backupToFile($this->backupFile);
        
        // Verify backup is a valid SQLite file by opening it
        $backupDb = new LibSQL($this->backupFile);
        $result = $backupDb->query('SELECT * FROM test');
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['data'])->toBe('backup_test');
        
        $backupDb->close();
    });
})->group('BackupAPI', 'Feature');

describe('Backup API - Data Integrity', function () {
    beforeEach(function () {
        $this->backupFile = __DIR__ . '/../../test_backup_integrity.db';
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
        
        $this->db = new LibSQL(':memory:');
        $this->db->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
        $this->db->execute('CREATE TABLE orders (id INTEGER PRIMARY KEY, user_id INTEGER, amount REAL)');
        
        // Insert test data
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Alice', 'alice@example.com')");
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Bob', 'bob@example.com')");
        $this->db->execute("INSERT INTO orders (user_id, amount) VALUES (1, 99.99)");
        $this->db->execute("INSERT INTO orders (user_id, amount) VALUES (2, 149.50)");
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
    });

    test('backup preserves all tables and data', function () {
        $this->db->backupToFile($this->backupFile);
        
        $backupDb = new LibSQL($this->backupFile);
        
        // Check tables exist
        $tables = $backupDb->query("SELECT name FROM sqlite_master WHERE type='table' ORDER BY name");
        $tableRows = $tables->fetchArray(LibSQL::LIBSQL_NUM);
        $tableNames = array_column($tableRows, 0);
        
        expect($tableNames)->toContain('users')
            ->and($tableNames)->toContain('orders');
        
        // Check user data
        $userCount = $backupDb->query('SELECT COUNT(*) as cnt FROM users');
        $userRows = $userCount->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($userRows[0]['cnt'])->toBe(2);
        
        // Check order data
        $orderTotal = $backupDb->query('SELECT SUM(amount) as total FROM orders');
        $orderRows = $orderTotal->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($orderRows[0]['total'])->toBe(249.49);
        
        $backupDb->close();
    });

    test('backup after modifications includes latest data', function () {
        // Backup initial state
        $this->db->backupToFile($this->backupFile);
        
        // Modify original
        $this->db->execute("INSERT INTO users (name, email) VALUES ('Charlie', 'charlie@example.com')");
        
        // Backup again to a different file
        $backupFile2 = __DIR__ . '/../../test_backup_integrity2.db';
        $this->db->backupToFile($backupFile2);
        
        // First backup should not have Charlie
        $backup1 = new LibSQL($this->backupFile);
        $result1 = $backup1->query("SELECT COUNT(*) as cnt FROM users");
        $rows1 = $result1->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows1[0]['cnt'])->toBe(2);
        $backup1->close();
        
        // Second backup should have Charlie
        $backup2 = new LibSQL($backupFile2);
        $result2 = $backup2->query("SELECT COUNT(*) as cnt FROM users");
        $rows2 = $result2->fetchArray(LibSQL::LIBSQL_ASSOC);
        expect($rows2[0]['cnt'])->toBe(3);
        $backup2->close();
        
        if (file_exists($backupFile2)) {
            unlink($backupFile2);
        }
    });
})->group('BackupAPI', 'Feature');

describe('Backup API - Error Handling', function () {
    beforeEach(function () {
        $this->db = new LibSQL(':memory:');
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
    });

    test('backup with empty destination throws error', function () {
        expect(fn() => $this->db->backupToFile(''))
            ->toThrow(Exception::class);
    });

    test('backup with invalid path throws error', function () {
        $invalidPath = '/nonexistent/deeply/nested/path/backup.db';
        
        expect(fn() => $this->db->backupToFile($invalidPath))
            ->toThrow(Exception::class);
    });
})->group('BackupAPI', 'Feature');

describe('Backup API - Local Database', function () {
    beforeEach(function () {
        $this->backupFile = __DIR__ . '/../../test_backup_local.db';
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
        
        $this->dbFile = __DIR__ . '/../../test_local_source.db';
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
        
        $this->db = new LibSQL($this->dbFile);
        $this->db->execute('CREATE TABLE local_test (id INTEGER PRIMARY KEY, value TEXT)');
        $this->db->execute("INSERT INTO local_test (value) VALUES ('local_data')");
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
        if (file_exists($this->dbFile)) {
            unlink($this->dbFile);
        }
    });

    test('backup local database to another file', function () {
        $result = $this->db->backupToFile($this->backupFile);
        
        expect($result)->toBeTrue()
            ->and(file_exists($this->backupFile))->toBeTrue();
        
        // Verify backup content
        $backupDb = new LibSQL($this->backupFile);
        $result = $backupDb->query('SELECT * FROM local_test');
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        
        expect($rows)->toHaveCount(1)
            ->and($rows[0]['value'])->toBe('local_data');
        
        $backupDb->close();
    });

    test('backup does not affect original database', function () {
        $this->db->backupToFile($this->backupFile);
        
        // Original should still be writable
        $this->db->execute("INSERT INTO local_test (value) VALUES ('after_backup')");
        
        $result = $this->db->query('SELECT COUNT(*) as cnt FROM local_test');
        $rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);
        
        expect($rows[0]['cnt'])->toBe(2);
    });
})->group('BackupAPI', 'Feature');

describe('Backup API - Large Database', function () {
    beforeEach(function () {
        $this->backupFile = __DIR__ . '/../../test_backup_large.db';
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
        
        $this->db = new LibSQL(':memory:');
        $this->db->execute('CREATE TABLE large_test (id INTEGER PRIMARY KEY, data TEXT, num INTEGER)');
        
        // Insert 100 rows
        for ($i = 0; $i < 100; $i++) {
            $this->db->execute('INSERT INTO large_test (data, num) VALUES (?, ?)', ["row_$i", $i]);
        }
    });

    afterEach(function () {
        if (isset($this->db)) {
            $this->db->close();
        }
        if (file_exists($this->backupFile)) {
            unlink($this->backupFile);
        }
    });

    test('backup large database preserves all rows', function () {
        $this->db->backupToFile($this->backupFile);
        
        $backupDb = new LibSQL($this->backupFile);
        $count = $backupDb->query('SELECT COUNT(*) as cnt FROM large_test');
        $rows = $count->fetchArray(LibSQL::LIBSQL_ASSOC);
        
        expect($rows[0]['cnt'])->toBe(100);
        
        // Verify sum
        $sum = $backupDb->query('SELECT SUM(num) as total FROM large_test');
        $sumRows = $sum->fetchArray(LibSQL::LIBSQL_ASSOC);
        // Sum of 0..99 = 4950
        expect($sumRows[0]['total'])->toBe(4950);
        
        $backupDb->close();
    });
})->group('BackupAPI', 'Feature');
