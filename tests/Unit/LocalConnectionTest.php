<?php

// Assert against Throwable, not Exception: argument-shape failures throw
// Error subclasses, which would pass a `not->toThrow(Exception::class)`
// check vacuously while no connection was ever opened. A bad DSN instead
// throws a regular Exception ("Failed to parse DSN"), so `toThrow(
// Exception::class)` below is a real assertion.
test('connects successfully with valid DSNs', function (string $dsn) {
    expect(fn() => new LibSQL($dsn))->not->toThrow(Throwable::class);
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
