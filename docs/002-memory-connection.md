# In-Memory Connection Usage Guide

Setting up a in-memory database connection using the LibSQL PHP Extension, there's a straightforward option available, utilizing the `:memory:` parameter. Here's a detailed explanation of how to use it:

**Using the `:memory:` Parameter:**

```php
$db = new LibSQL(":memory:");
```

- **Usage Explanation:**
  - The `:memory:` parameter allows you to create an in-memory SQLite database.
  - This means that the database resides entirely in memory and is not persisted to disk.
  - It's useful for temporary or transient data storage needs within your application.

**Considerations:**

- **Temporary Data Storage:** Since the database exists only in memory, any data stored in it will be lost once the connection is closed or the script terminates.
- **Performance:** In-memory databases can offer faster read and write operations compared to disk-based databases, as they bypass disk I/O operations.
- **Limited Persistence:** As the data is not stored on disk, it's not suitable for long-term storage or persistent data requirements.

**Example Use Case:**

```php
// Create an in-memory SQLite database connection
$db = new LibSQL(":memory:");

// Perform database operations, such as creating tables, inserting data, etc.

// Close the database connection when done
// $db->close(); // (Note: Uncomment this line if required)
```

Utilizing the `:memory:` parameter with the LibSQL PHP Extension enables you to create lightweight, in-memory SQLite databases for temporary data storage purposes. While it offers fast read and write operations, it's important to consider its limitations, such as data persistence and scope, when incorporating it into your application architecture.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - **[In-Memory Connection](002-memory-connection.md)**
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

