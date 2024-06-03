# LibSQL `close` Method

## Description

The `close` method in the LibSQL PHP Extension is used to close the database connection, releasing any resources associated with it. It should be called when you're done using the database connection to free up system resources and prevent memory leaks.

## Method Signature

```php
public function close(): void
```

## Parameters

This method does not accept any parameters.

## Return Value

This method does not return any value (`void`).

## Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

// Perform database operations...

// Close the database connection when done
$db->close();
```

## Notes

- Always close the database connection when you no longer need it to free up resources and prevent memory leaks.
- Closing the database connection should be the last step in your script after you've finished all database operations.
- Once the connection is closed, you won't be able to perform any further operations on it unless you establish a new connection.
- Failure to close the database connection may result in resource leaks and could impact the performance of your application over time.

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
    - **[Close](014-close.md)**
    - [Sync](015-sync.md)
- [LibSQLStatement](016-LibSQLStatement.md)
- [LibSQLTransaction](017-LibSQLTransaction.md)
