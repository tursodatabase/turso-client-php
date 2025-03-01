<?php

test('connects successfully with valid DSNs', function (string $dsn) {
    expect(fn() => new LibSQL($dsn))->not->toThrow(Exception::class);
})->with([
    'libsql:dbname=database.db',
    'database.db',
    'file:database.db',
])->group('LocalConnectionTest', 'DatabaseConnectionTest');

test('fails with invalid DSNs', function (string $dsn) {
    expect(fn() => new LibSQL($dsn))->toThrow(Exception::class);
})->with([
    'libsql:database.db',
    '',
])->group('LocalConnectionTest', 'DatabaseConnectionTest');
