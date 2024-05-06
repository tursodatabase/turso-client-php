# LibSQL `query` Method

## Description

The `query` method in the LibSQL PHP Extension facilitates the execution of an SQL query on the database. This method allows you to retrieve data from the database based on the provided SQL query. It supports parameterized queries for improved security and flexibility.

## Method Signature

```php
public function query(string $stmt, array $parameters = []): array
```

## Parameters

- `$stmt` (string): The SQL query to execute.
- `$parameters` (array): An optional array containing parameters for the SQL statement. The parameters can be provided as positional (numeric) or named parameters (assosiative).

## Return Value

- `array`: Returns the result of the query as an associative array.

## Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

// SQL query with parameterized query
$stmt = "SELECT * FROM users WHERE age > :age";

// Parameters for the parameterized query
$parameters = [':age' => 30];

// Execute the query
$result = $db->query($stmt, $parameters);

// Process the query result
foreach ($results['rows'] as $row) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . "\n";
}
```

## Notes

- Use parameterized queries to prevent SQL injection attacks and improve query performance.
- The `$parameters` array should contain key-value pairs where keys represent parameter placeholders in the SQL query (e.g., `:age`) and values represent the actual parameter values.
- The result of the query is returned as an associative array, where each element represents a row of the result set.
- Ensure proper error handling for database query failures to gracefully handle exceptions and errors during query execution.

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
    - **[Query](011-query.md)**
    - [Transaction](012-transaction.md)
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
- [LibSQLStatement](015-LibSQLStatement.md)
- [LibSQLTransaction](016-LibSQLTransaction.md)
