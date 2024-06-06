# LibSQL `isAutocommit` Method

## Description

The `isAutocommit()` method in the LibSQL PHP Extension checks whether autocommit mode is enabled for the current LibSQL connection. Autocommit mode automatically commits each SQL statement as a separate transaction, ensuring that changes made by each statement are immediately applied to the database.

## Method Signature

```php
public function isAutocommit(): bool
```

## Parameters

This method does not accept any parameters.

## Return Value

- `bool`: Returns `true` if autocommit mode is enabled for the connection, otherwise returns `false`.

## Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

// Check if autocommit mode is enabled
if ($db->isAutocommit()) {
    echo "Autocommit mode is enabled.";
} else {
    echo "Autocommit mode is disabled.";
}
```

## Notes

- By default, autocommit mode is usually enabled for LibSQL connections.
- When autocommit mode is disabled, multiple SQL statements can be grouped into a single transaction using the `transaction()` method, allowing for more complex operations that require atomicity and consistency across multiple statements.
- It is important to understand the implications of enabling or disabling autocommit mode, as it can affect the behavior and performance of database operations.

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
    - **[Is Auto Commit](008-isAutocommit.md)**
    - [Execute](009-execute.md)
    - [Execute Batch](010-executeBatch.md)
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
    - [Sync](015-sync.md)
- [LibSQLStatement](016-LibSQLStatement.md)
- [LibSQLTransaction](017-LibSQLTransaction.md)
