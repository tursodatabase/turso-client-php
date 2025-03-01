<?php

$authToken = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJFZERTQSJ9.eyJpYXQiOjE3NDA2Nzc2ODUsIm5iZiI6MTc0MDY3NzY4NSwiZXhwIjoxNzQxMjgyNDg1LCJqdGkiOiJkYjEifQ.6qW2iglFGkiEDZ9IAp0CL5n2zpz_SlD8EwcSDwEurOdQ9d8qrppek5qJ5rXTyH80hyHi5CruaFsvmkcUZg_UBg';

$config = [
    "url" => "file:database.db",
    "authToken" => $authToken,
    "syncUrl" => "http://127.0.0.1:8080",
    "syncInterval" => 5,
    "read_your_writes" => true,
    "encryptionKey" => "",
];

test('embedded replica connection', function () use ($config) {
    expect(fn() => new LibSQL(config: $config, offline_writes: false))->not->toThrow(Exception::class);
})->group('EmbeddedReplicaConnectionTest', 'DatabaseConnectionTest');