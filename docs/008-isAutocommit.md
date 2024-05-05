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
