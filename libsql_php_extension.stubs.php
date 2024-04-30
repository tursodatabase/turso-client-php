<?php

// Stubs for libsql_php_extension

namespace {
    class LibSQLPHP {
        public $mode;

        public $conn_id;

        public function __construct(array $config) {}

        public static function version(): string {}

        public function changes(): int {}

        public function isAutocommit(): bool {}

        public function exec(string $stmt): bool {}

        public function query(string $stmt, ?array $positional, ?array $named): mixed {}
    }
}
