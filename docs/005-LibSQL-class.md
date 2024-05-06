# LibSQL PHP Extension

## Introduction

The LibSQL PHP Extension facilitates connections to LibSQL databases from PHP applications, offering a range of functionalities to streamline database operations. This documentation provides a detailed overview of the LibSQL class and its methods.

## Class Synopsis

```php
class LibSQL
{
    // Constants
    const OPEN_READONLY = 1;
    const OPEN_READWRITE = 2;
    const OPEN_CREATE = 4;

    // Properties
    public $mode;

    // Methods
    public function __construct(string|array $config, ?int $flags = 6, ?string $encryption_key = "");
    public static function version(): string;
    public function changes(): int;
    public function isAutocommit(): bool;
    public function execute(string $stmt, ?array $parameters = []): int;
    public function executeBatch(string $stmt): bool;
    public function query(string $stmt, ?array $parameters = []): array;
    public function transaction(?string $behavior = "DEFERRED"): LibSQLTransaction;
    public function prepare(string $sql): LibSQLStatement;
    public function close(): void;
}
```

## Table of Contents

- [`__construct(string|array $config, ?int $flags = 6, ?string $encryption_key = "")`](quick-start.md): Creates a new LibSQL instance.
- [`version(): string`](006-version.md): Retrieves the version of the LibSQL library.
- [`changes(): int`](007-changes.md): Retrieves the number of rows changed by the last SQL statement.
- [`isAutocommit(): bool`](008-isAutocommit.md): Checks if autocommit mode is enabled for the connection.
- [`execute(string $stmt, array $parameters = []): int`](009-execute.md): Executes an SQL statement on the database.
- [`executeBatch(string $stmt): bool`](010-executeBatch.md): Executes a batch of SQL statements on the database.
- [`query(string $stmt, array $parameters = []): array`](011-query.md): Executes an SQL query on the database.
- [`transaction(string $behavior = "DEFERRED"): LibSQLTransaction`](012-transaction.md): Initiates a new database transaction.
- [`prepare(string $sql): LibSQLStatement`](013-prepare.md): Prepares an SQL statement for execution.
- [`close(): void`](014-close.md): Closes the database connection.

## Description

- **__construct(string|array $config, ?int $flags = 6, ?string $encryption_key = "")**: Initializes a new LibSQL instance with the provided configuration parameters.

- **version(): string**: Retrieves the version of the LibSQL library.

- **changes(): int**: Returns the number of rows changed by the last SQL statement executed.

- **isAutocommit(): bool**: Checks if autocommit mode is enabled for the connection.

- **execute(string $stmt, array $parameters = []): int**: Executes an SQL statement on the database with optional parameters and returns the number of affected rows.

- **executeBatch(string $stmt): bool**: Executes a batch of SQL statements on the database.

- **query(string $stmt, array $parameters = []): array**: Executes an SQL query on the database with optional parameters and returns the result set.

- **transaction(string $behavior = "DEFERRED"): LibSQLTransaction**: Initiates a new database transaction with the specified behavior.

- **prepare(string $sql): LibSQLStatement**: Prepares an SQL statement for execution.

- **close(): void**: Closes the database connection.

## Example

```php
// Creating a new LibSQL instance
$db = new LibSQL(":memory:");

// Retrieving the version of the LibSQL library
$version = LibSQL::version();
```

## See Also

- [LibSQLStatement](015-LibSQLStatement.md): Documentation for the LibSQLStatement class.
- [LibSQLTransaction](016-LibSQLTransaction.md): Documentation for the LibSQLTransaction class.

## Notes

Ensure proper error handling and data validation when using the LibSQL PHP Extension to handle potential errors and ensure data integrity.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - [In-Memory Connection](002-memory-connection.md)
    - [Remote Connection](003-remote-connection.md)
    - [Remote Replica Connection](004-remote-replica-connection.md)
- **[LibSQL Class](005-LibSQL-class.md)**
    - [Version](006-version.md)
    - [Changes](007-changes.md)
    - [Is Auto Commit](008-isAutocommit.md)
    - [Execute](009-execute.md)
    - [Execute Batch](010-executeBatch.md)
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
- [LibSQLStatement](015-LibSQLStatement.md)
- [LibSQLTransaction](016-LibSQLTransaction.md)
