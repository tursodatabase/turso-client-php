<?php

test('successfully connect in-memory database', function () {
    expect(fn() => new LibSQL(':memory:'))->not->toThrow(Exception::class);
})->group('InMemoryConnectionTest', 'DatabaseConnectionTest');

test('fails with invalid in-memory connection', function () {
    expect(fn() => new LibSQL(''))->toThrow(Exception::class);
})->group('InMemoryConnectionTest', 'DatabaseConnectionTest');