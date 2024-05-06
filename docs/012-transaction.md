# LibSQL `transaction` Method

## Description

The `transaction` method in the LibSQL PHP Extension initiates a new database transaction, allowing multiple SQL statements to be executed as an atomic unit of work. This method provides control over transaction behavior, such as deferred or immediate transaction initiation.

## Method Signature

```php
public function transaction(string $behavior = "DEFERRED"): LibSQLTransaction
```

## Parameters

- `$behavior` (string): Optional. Specifies the behavior of the transaction. Possible values are `"DEFERRED"`, `"READ"`, or `"WRITE"`. Default is `"DEFERRED"`.

## Return Value

- `LibSQLTransaction`: Returns a transaction object that can be used to manage the transaction.

## Example

```php
// Create a new LibSQL instance
$db = new LibSQL("database.db");

// Start a new transaction with default behavior
$transaction = $db->transaction();

// Start a new transaction with write behavior
$writeTransaction = $db->transaction("WRITE");

// Start a new transaction with read behavior
$readTransaction = $db->transaction("READ");
```

## Notes

- Transactions ensure data integrity by allowing a series of SQL statements to be treated as a single unit of work, either all succeeding or all failing.
- The `$behavior` parameter determines the concurrency control behavior of the transaction. `"DEFERRED"` delays the acquisition of database locks until they are needed, `"WRITE"` acquires locks as soon as the transaction begins, and `"READ"` prevents other transactions from accessing the database until the transaction completes.
- Always commit or rollback transactions after their execution to release locks and maintain database consistency.
- Handle exceptions and errors appropriately within transaction blocks to ensure proper error recovery and transaction management.

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
    - **[Transaction](012-transaction.md)**
    - [Prepare](013-prepare.md)
    - [Close](014-close.md)
- [LibSQLStatement](015-LibSQLStatement.md)
- [LibSQLTransaction](016-LibSQLTransaction.md)
