# LibSQLTransaction Class

## Introduction

The `LibSQLTransaction` class represents a database transaction in the LibSQL PHP Extension. It provides methods to manage database transactions, execute SQL statements within transactions, and retrieve transaction details.

## Class Synopsis

```php
class LibSQLTransaction
{
    // Methods
    public function __construct(string $conn_id, string $trx_mode);
    public function changes(): int;
    public function isAutocommit(): bool;
    public function exec(string $stmt, array $parameters = []): int;
    public function query(string $stmt, array $parameters = []): array;
    public function commit(): void;
    public function rollback(): void;
}
```

## Table of Contents

- [`__construct(string $conn_id, string $trx_mode)`](#constructor): Creates a new `LibSQLTransaction` instance.
- [`changes(): int`](#changes): Retrieves the number of rows changed by the last SQL statement executed within the transaction.
- [`isAutocommit(): bool`](#isAutocommit): Checks if the transaction is set to autocommit.
- [`exec(string $stmt, array $parameters = []): int`](#exec): Executes an SQL statement within the transaction and returns the number of affected rows.
- [`query(string $stmt, array $parameters = []): array`](#query): Executes a query within the transaction and returns the result set.
- [`commit(): void`](#commit): Commits the transaction.
- [`rollback(): void`](#rollback): Rolls back the transaction.

## Description

- **__construct(string $conn_id, string $trx_mode)**: Construct by [`LibSQL::transaction()`](012-transaction.md).

- **changes(): int**: Returns the number of rows changed by the last SQL statement executed within the transaction.

- **isAutocommit(): bool**: Checks if the transaction is set to autocommit.

- **exec(string $stmt, array $parameters = []): int**: Executes an SQL statement within the transaction with optional parameters and returns the number of affected rows.

- **query(string $stmt, array $parameters = []): array**: Executes a query within the transaction with optional parameters and returns the result set as an array.

- **commit(): void**: Commits the transaction, making all changes permanent.

- **rollback(): void**: Rolls back the transaction, undoing all changes made within the transaction.

## Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

$tx = $db->transaction();

$tx->exec("INSERT INTO users (name, age) VALUES (:name, :age)", [
    ":name" => "Soimah Pancawati",
    ":age" => "37"
]);

if (false) {
    $tx->commit();
} else {
    $tx->rollback();
}
```

## Notes

Ensure proper error handling and transaction management when using the `LibSQLTransaction` class to handle potential errors and ensure data integrity.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - [In-Memory Connection](002-memory-connection.md)
    - [Remote Connection](003-remote-connection.md)
    - [Remote Replica Connection](004-remote-replica-connection.md)
- [LibSQL Class](005-LibSQL-class.md)
    - [Version](006-version.md)
    - [Changes](007-changes.md)
    - [Is Auto Commit](008-isAutocommit.md)
    - [Execute](009-execute.md)
    - [Execute Batch](010-executeBatch.md)
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
    - [Sync](015-sync.md)
- [LibSQLStatement](016-LibSQLStatement.md)
- **[LibSQLTransaction](017-LibSQLTransaction.md)**
