<?php

$authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDA2Nzc2ODUsIm5iZiI6MTc0MDY3NzY4NSwiZXhwIjoxNzQxMjgyNDg1LCJqdGkiOiJkYjEifQ.6qW2iglFGkiEDZ9IAp0CL5n2zpz_SlD8EwcSDwEurOdQ9d8qrppek5qJ5rXTyH80hyHi5CruaFsvmkcUZg_UBg';

test('remote connection using http or https protocol', function () use ($authToken) {
    expect(fn() => new LibSQL('libsql:dbname=http://127.0.0.1:8080;authToken=' . $authToken))->not->toThrow(Exception::class);
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');

test('remote connection using libsql protocol', function () use ($authToken) {
    expect(fn() => new LibSQL('libsql:dbname=libsql://127.0.0.1:8080;authToken=' . $authToken))->not->toThrow(Exception::class);
})->group('RemoteConnectionTest', 'DatabaseConnectionTest');

