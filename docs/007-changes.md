# LibSQL `changes` Method

## Description

The `changes()` method in the LibSQL PHP Extension retrieves the number of rows changed by the last SQL statement executed using the LibSQL connection. This method is particularly useful for applications that need to track the number of rows affected by insert, update, or delete operations.

## Method Signature

```php
public function changes(): int
```

## Parameters

This method does not accept any parameters.

## Return Value

- `int`: The number of rows changed by the last SQL statement.

## Example

```php
// Execute an SQL statement
$db = new LibSQL("database.db");
$stmt = "UPDATE table SET column = value WHERE condition";
$db->execute($stmt);

// Retrieve the number of rows changed
$changes = $db->changes();
echo "Number of Rows Changed: " . $changes;
```

## Notes

- The `changes()` method can only be called after executing an SQL statement that modifies data.
- It is recommended to handle exceptions and errors appropriately when using the `changes()` method to ensure smooth execution and graceful error handling in case of any issues.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - [In-Memory Connection](002-memory-connection.md)
    - [Remote Connection](003-remote-connection.md)
    - [Remote Replica Connection](004-remote-replica-connection.md)
- [LibSQL Class](005-LibSQL-class.md)
    - [Version](006-version.md)
    - **[Changes](007-changes.md)**
    - [Is Auto Commit](008-isAutocommit.md)
    - [Execute](009-execute.md)
    - [Execute Batch](010-executeBatch.md)
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
- [LibSQLStatement](015-LibSQLStatement.md)
- [LibSQLTransaction](016-LibSQLTransaction.md)
