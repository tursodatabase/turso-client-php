# LibSQL `sync` Method

## Description

The `sync` method in the LibSQL PHP Extension is used to sync changes from the remote database to the local replica, [embedded replicas](https://docs.turso.tech/features/embedded-replicas/introduction) provide a smooth switch between local and remote database operations, allowing the same database to adapt to various scenarios effortlessly. They also ensure speedy data access by syncing local copies with the remote database, enabling microsecond-level read operations — a significant advantage for scenarios demanding quick data retrieval.


## Method Signature

```php
public function sync(): void
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

// Sync the database
$db->sync();
```

## Notes

- Do not open the local database while the embedded replica is syncing. This can lead to data corruption.
- In certain contexts, such as serverless environments without a filesystem, you can’t use embedded replicas.

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
    - **[Sync](015-sync.md)**
- [LibSQLStatement](016-LibSQLStatement.md)
- [LibSQLTransaction](017-LibSQLTransaction.md)
