# LibSQLStatement Class

## Introduction

The `LibSQLStatement` class represents a prepared SQL statement in the LibSQL PHP Extension. It provides methods to execute the prepared statement, retrieve results, and manage parameters.

## Class Synopsis

```php
class LibSQLStatement
{
    // Methods
    public function __construct(string $conn_id, string $sql);
    public function finalize(): void;
    public function execute(array $parameters): int;
    public function query(array $parameters): array;
    public function reset(): void;
    public function parameterCount(): int;
    public function parameterName(int $idx): string;
    public function columns(): array;
}
```

## Table of Contents

- [`__construct(string $conn_id, string $sql)`](#constructor): Construct a `LibSQLStatement` Object.
- [`finalize(): void`](#finalize): Finalizes the prepared statement.
- [`execute(array $parameters): int`](#execute): Executes the prepared statement with given parameters and returns the number of affected rows.
- [`query(array $parameters): array`](#query): Executes the prepared statement and retrieves the result set.
- [`reset(): void`](#reset): Resets the prepared statement.
- [`parameterCount(): int`](#parameterCount): Gets the number of parameters in the prepared statement.
- [`parameterName(int $idx): string`](#parameterName): Gets the name of a parameter by index.
- [`columns(): array`](#columns): Gets the column names of the result set.

## Description

- **__construct(string $conn_id, string $sql)**: instances are created by [`LibSQL::prepare()`](013-prepare.md).

- **finalize()**: Finalizes the prepared statement, releasing any resources associated with it.

- **execute(array $parameters): int**: Executes the prepared statement with the specified parameters and returns the number of affected rows.

- **query(array $parameters): array**: Executes the prepared statement with the specified parameters and retrieves the result set as an array.

- **reset()**: Resets the prepared statement, clearing any previously set parameters or results.

- **parameterCount(): int**: Returns the number of parameters in the prepared statement.

- **parameterName(int $idx): string**: Returns the name of the parameter at the specified index.

- **columns(): array**: Returns an array containing the column names of the result set.

## Example

```php
// Creating a new LibSQL instance
$db = new LibSQL("database.db");
$stmt = $db->prepare("SELECT * FROM users WHERE id = ? AND age > ?");

// Executing the prepared statement
$affectedRows = $stmt->execute([$param1, $param2]);

// Retrieving the result set
$resultSet = $stmt->query([$param1, $param2]);

// Resetting the prepared statement
$stmt->reset();

// Getting the number of parameters
$paramCount = $stmt->parameterCount();

// Getting the name of a parameter
$paramName = $stmt->parameterName($index);

// Getting the column names of the result set
$columns = $stmt->columns();

$db->close();
```

## Notes

Ensure proper error handling and parameter validation when using the `LibSQLStatement` class to handle potential errors and ensure data integrity.

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
- **[LibSQLStatement](015-LibSQLStatement.md)**
- [LibSQLTransaction](016-LibSQLTransaction.md)
