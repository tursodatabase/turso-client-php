<?php

$authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDA2Nzc2ODUsIm5iZiI6MTc0MDY3NzY4NSwiZXhwIjoxNzQxMjgyNDg1LCJqdGkiOiJkYjEifQ.6qW2iglFGkiEDZ9IAp0CL5n2zpz_SlD8EwcSDwEurOdQ9d8qrppek5qJ5rXTyH80hyHi5CruaFsvmkcUZg_UBg';

// Assert against Throwable, not Exception: argument-shape failures throw
// Error subclasses, which would pass a `not->toThrow(Exception::class)`
// check vacuously. Remote connections open lazily — construction performs
// no network work, so these succeed even with nothing listening on the
// port; failures surface at first use instead (see the test below).
test('remote connection using http or https protocol', function () use ($authToken) {
    expect(fn() => new LibSQL('libsql:dbname=http://127.0.0.1:8080;authToken=' . $authToken))->not->toThrow(Throwable::class);
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');

test('remote connection using libsql protocol', function () use ($authToken) {
    expect(fn() => new LibSQL('libsql:dbname=libsql://127.0.0.1:8080;authToken=' . $authToken))->not->toThrow(Throwable::class);
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');

// Because of the lazy opening, an unreachable remote does not fail the
// constructor — the first statement does, with the Hrana connection error.
// Port 9 ("discard") is used so that nothing can be listening on it in a
// normal environment.
test('remote connection fails at first use with an unreachable server', function () use ($authToken) {
    $db = new LibSQL('libsql:dbname=http://127.0.0.1:9;authToken=' . $authToken);

    try {
        expect(fn() => $db->execute("SELECT 1"))->toThrow(Exception::class);
    } finally {
        $db->close();
    }
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');
