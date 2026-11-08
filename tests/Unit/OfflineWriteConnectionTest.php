<?php

$authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDA2Nzc2ODUsIm5iZiI6MTc0MDY3NzY4NSwiZXhwIjoxNzQxMjgyNDg1LCJqdGkiOiJkYjEifQ.6qW2iglFGkiEDZ9IAp0CL5n2zpz_SlD8EwcSDwEurOdQ9d8qrppek5qJ5rXTyH80hyHi5CruaFsvmkcUZg_UBg';

$config = [
    "url" => "file:database.db",
    "authToken" => $authToken,
    "syncUrl" => "http://127.0.0.1:8000",
    "syncInterval" => 5,
    "read_your_writes" => true,
    "encryptionKey" => "",
];

// Named parameters that follow an omitted optional parameter must be passed
// explicitly for extension functions: PHP cannot fill in the unknown defaults
// of the skipped slots. Assert against Throwable, not Exception — the
// "must be passed explicitly" failure is an Error subclass and would pass a
// `not->toThrow(Exception::class)` check vacuously.
test('embedded replica connection + offline write', function () use ($config) {
    expect(fn() => new LibSQL(
        config: $config,
        sqld_offline_mode: true,
        flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
        encryption_key: "",
        offline_writes: true
    ))->not->toThrow(Throwable::class);
})->group('OfflineWriteConnectionTest', 'DatabaseConnectionTest');

// The offline write journal must initialize itself on a database file that
// does not exist yet: first-run connects otherwise fail with
// "no such table: libsql_metadata".
test('offline write journal initializes on a fresh database file', function () {
    $file = sys_get_temp_dir() . '/turso-p2-offline-' . uniqid() . '.db';
    $config = [
        "url" => "file:" . $file,
        "authToken" => "bogus-token-for-offline-test",
        "syncUrl" => "http://127.0.0.1:9",
        "syncInterval" => 5,
        "read_your_writes" => true,
        "encryptionKey" => "",
    ];

    try {
        $db = new LibSQL(
            config: $config,
            sqld_offline_mode: true,
            flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
            encryption_key: "",
            offline_writes: true
        );

        $db->execute("CREATE TABLE turso_p2_fresh (v INTEGER)");
        $db->execute("INSERT INTO turso_p2_fresh VALUES (1)");

        expect($db->getPendingOperationsCount())->toBeGreaterThanOrEqual(1);

        $row = $db->query("SELECT v FROM turso_p2_fresh")->fetchSingle(LibSQL::LIBSQL_BOTH);
        expect((int)($row['v'] ?? 0))->toBe(1);

        $db->close();
    } finally {
        @unlink($file);
    }
})->group('OfflineWriteConnectionTest', 'DatabaseConnectionTest');
