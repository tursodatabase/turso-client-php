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
