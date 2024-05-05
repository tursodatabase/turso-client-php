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
