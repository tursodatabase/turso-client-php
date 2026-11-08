<?php

// Assert against Throwable, not Exception: argument-shape failures throw
// Error subclasses (e.g. ArgumentCountError from skipped optional named
// arguments), which would pass a `not->toThrow(Exception::class)` check
// vacuously while no connection was opened.
test('successfully connect in-memory database', function () {
    $db = new LibSQL(':memory:');

    // In-memory connections are immediately usable, not just constructible.
    $db->execute("CREATE TABLE im_roundtrip (v TEXT)");
    $db->execute("INSERT INTO im_roundtrip VALUES ('in-memory round trip')");
    $row = $db->query("SELECT v FROM im_roundtrip")->fetchSingle(LibSQL::LIBSQL_ASSOC);

    expect($row['v'] ?? '')->toBe('in-memory round trip');
    $db->close();
})->group('InMemoryConnectionTest', 'DatabaseConnectionTest');

// An empty DSN is rejected at parse time with "Failed to parse DSN".
test('fails with invalid in-memory connection', function () {
    expect(fn() => new LibSQL(''))->toThrow(Exception::class);
})->group('InMemoryConnectionTest', 'DatabaseConnectionTest');
