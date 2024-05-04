<?php

// Stubs for libsql_php_extension

namespace {
    class LibSQLTransaction {
        public $trx_behavior;

        public $trx_id;

        public $conn_id;

        public function __construct(string $conn_id, string $trx_mode) {}

        public function changes(): int {}

        public function isAutocommit(): bool {}

        public function exec(string $stmt): bool {}

        public function query(string $stmt, mixed $parameters): mixed {}

        public function commit(): mixed {}

        public function rollback(): mixed {}
    }

    class LibSQL {
        const OPEN_READONLY = null;

        const OPEN_READWRITE = null;

        const OPEN_CREATE = null;

        public $mode;

        public $conn_id;

        public function __construct(array $config) {}

        public static function version(): string {}

        public function changes(): int {}

        public function isAutocommit(): bool {}

        public function exec(string $stmt): bool {}

        public function query(string $stmt, mixed $parameters): mixed {}

        public function transaction(?string $behavior): \LibSQLTransaction {}

        public function close(): mixed {}
    }
}
