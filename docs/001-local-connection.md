## Local Connection Usage Guide

Local databases using the LibSQL PHP Extension, there are several straightforward options available. Let's explore each option in detail:

**Option 1: Standard DSN Connection:**

This option involves using a Data Source Name (DSN) string to connect to the desired local database.

```php
$db = new LibSQL("libsql:dbname=database.db");
```

- **Usage Explanation:**
  - The DSN string format is `libsql:dbname=database.db`, where `"database.db"` represents the name of the local database file.
  - This option is suitable for connecting to a database using the LibSQL protocol with the specified database name.

**Option 2: Standard SQLite Connection:**

In this option, you directly specify the filename of the SQLite database without using a DSN string.

```php
$db = new LibSQL("database.db");
```

- **Usage Explanation:**
  - Here, `"database.db"` is the filename of the SQLite database.
  - This option simplifies the connection process by directly referencing the database file.

**Option 3: Standard LibSQL Connection:**

This option is similar to the first option but uses the file protocol in the DSN string.

```php
$db = new LibSQL("file:database.db");
```

- **Usage Explanation:**
  - The DSN string format is `file:database.db`, where `"database.db"` is the name of the local database file.
  - It allows for connecting to a database using the LibSQL protocol with the specified file name.

**Error Handling:**

It's important to note that incorrect usage of the connection parameters may result in errors. For example:

```php
// Error: PHP Fatal error:  Uncaught Exception: Failed to parse DSN
$db = new LibSQL("libsql:database.db");

// Error: PHP Fatal error:  Uncaught Exception: Failed to parse DSN
$db = new LibSQL("");
```

- **Error Explanation:**
  - Providing an invalid DSN string, such as missing required parameters or an empty string, will result in a parsing error.
  - Ensure that the DSN string is correctly formatted and contains the necessary parameters for establishing a connection.

By following these usage guidelines and ensuring the correct format of the DSN string, you can effectively establish connections with local databases using the LibSQL PHP Extension.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - **[Local Connection](001-local-connection.md)**
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
    - [Sync](015-sync.md)
- [LibSQLStatement](016-LibSQLStatement.md)
- [LibSQLTransaction](017-LibSQLTransaction.md)

