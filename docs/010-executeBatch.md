# LibSQL `executeBatch` Method

## Description

The `executeBatch` method in the LibSQL PHP Extension facilitates the execution of a batch of SQL statements on the database. This method is particularly useful when multiple SQL statements need to be executed together as a single unit, offering efficiency and atomicity in database operations.

## Method Signature

```php
public function executeBatch(string $stmt): bool
```

## Parameters

- `$stmt` (string): The batch of SQL statements to execute as a single string.

## Return Value

- `bool`: Returns `true` if the batch execution was successful, otherwise `false`.

## Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

// SQL statements to execute as a batch
$stmt = "
    CREATE TABLE users (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        age INTEGER
    );
    INSERT INTO users (name, age) VALUES ('John Doe', 30);
    INSERT INTO users (name, age) VALUES ('Jane Smith', 25);
    INSERT INTO users (name, age) VALUES ('Michael Johnson', 40);
";

// Execute the batch of SQL statements
if ($db->executeBatch($stmt)) {
    echo "Batch execution successful.";
} else {
    echo "Batch execution failed.";
}
```

## Notes

- SQL statements within the batch are separated by semicolons (`;`).
- This method executes all SQL statements within the batch as a single transaction, ensuring atomicity.
- It is important to ensure that the SQL statements within the batch are logically related and do not violate database integrity constraints.
- Batch execution can improve performance by reducing the overhead of multiple database connections and transactions. However, it should be used judiciously to avoid excessive load on the database server.

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
    - **[Execute Batch](010-executeBatch.md)**
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
    - [Sync](015-sync.md)
- [LibSQLStatement](016-LibSQLStatement.md)
- [LibSQLTransaction](017-LibSQLTransaction.md)
