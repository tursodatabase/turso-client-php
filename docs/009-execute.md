### LibSQL `execute` Method

#### Description

The `execute` method in the LibSQL PHP Extension allows executing SQL statements with positional or named parameters, similar to how SQLite provides parameterized queries.

#### Method Signature

```php
public function execute(string $stmt, array $parameters = []): int
```

#### Parameters

- `$stmt` (string): The SQL statement to execute.
- `$parameters` (array): An optional array containing parameters for the SQL statement. The parameters can be provided as positional (numeric) or named parameters (assosiative).

#### Return Value

- `int`: Returns the number of rows affected by the executed SQL statement.

#### Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

// SQL statement with positional parameters
$stmt = "INSERT INTO users (name, age) VALUES (?, ?)";
$parameters = ["John Doe", 30];
$rowsAffected = $db->execute($stmt, $parameters);
echo "Inserted $rowsAffected rows.";

// SQL statement with named parameters
$stmt = "UPDATE products SET price = :price WHERE id = :id";
$parameters = [":price" => 99.99, ":id" => 123];
$rowsAffected = $db->execute($stmt, $parameters);
echo "Updated $rowsAffected rows.";
```

#### Notes

- Positional parameters are represented by `?` in the SQL statement and are replaced by values provided in the `$parameters` array in the order they appear.
- Named parameters are represented by placeholders like `:name` or `@name` in the SQL statement and are replaced by corresponding key-value pairs in the `$parameters` array.
- Using parameterized queries helps prevent SQL injection attacks by separating SQL logic from data, allowing the database engine to distinguish between SQL code and user input.
- This method supports both INSERT, UPDATE, DELETE, and other SQL statements that modify data in the database.
- It is recommended to use parameterized queries whenever user input is involved to ensure the security and integrity of the database.

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
    - **[Execute](009-execute.md)**
    - [Execute Batch](010-executeBatch.md)
    - [Query](011-query.md)
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
- [LibSQLStatement](015-LibSQLStatement.md)
- [LibSQLTransaction](016-LibSQLTransaction.md)
