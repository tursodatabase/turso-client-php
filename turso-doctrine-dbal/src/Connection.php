<?php

declare(strict_types=1);

namespace Turso\Doctrine\DBAL;

use Doctrine\DBAL\Driver\Connection as ConnectionInterface;
use Doctrine\DBAL\Driver\Exception\NoIdentityValue;
use LibSQL;

final class Connection implements ConnectionInterface
{
    private string $sql;
    public function __construct(
        private readonly LibSQL $connection
    ) {
    }

    public function prepare(string $sql): Statement
    {
        try {
            $this->sql = $sql;
            $statement = $this->connection->prepare($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        \assert($statement !== false);

        return new Statement($this->connection, $statement, $sql);
    }

    public static function escapeString($value)
    {
        // DISCUSSION: Open PR if you have best approach
        $escaped_value = str_replace(
            ["\\", "\x00", "\n", "\r", "\x1a", "'", '"'],
            ["\\\\", "\\0", "\\n", "\\r", "\\Z", "\\'", '\\"'],
            $value
        );

        return $escaped_value;
    }

    public function quote(string $value): string
    {
        return self::escapeString($value);
    }

    public function query(string $sql): Result
    {
        $this->sql = $sql;
        return new Result($this->connection->query($sql));
    }

    public function exec(string $sql): int
    {
        $changes = 0;
        $this->sql = $sql;

        try {
            $changes = $this->connection->execute($sql);
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        return $changes;
    }

    public function lastInsertId(): int
    {
        $result = $this->connection->query($this->sql);
        if (empty($result['rows'])) {
            throw NoIdentityValue::new();
        }

        return $result['last_insert_rowid'];
    }

    public function beginTransaction(): void
    {
        try {
            $this->connection->execute('BEGIN');
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
    }

    public function commit(): void
    {
        try {
            $this->connection->execute('COMMIT');
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
    }

    public function rollBack(): void
    {
        try {
            $this->connection->execute('ROLLBACK');
        } catch (\Exception $e) {
            throw Exception::new($e);
        }
    }

    public function getNativeConnection(): LibSQL
    {
        return $this->connection;
    }

    public function getServerVersion(): string
    {
        return LibSQL::version();
    }
}
