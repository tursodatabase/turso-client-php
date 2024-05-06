# Remote Connection Usage Guide

When establishing connections to remote databases using the LibSQL PHP Extension, you have two options available. Both options utilize the LibSQL class constructor to create a new database connection object. Let's delve into each option:

**Option 1: Standard DSN Connection with 'libsql://' Protocol:**

```php
$db = new LibSQL("libsql:dbname=libsql://database-org.turso.io;authToken=random-token");
```

- **Usage Explanation:**
  - This option employs the `libsql://` protocol in the DSN string to connect to the remote database.
  - The DSN string specifies the database name as `libsql://database-org.turso.io`.
  - Additionally, it requires an authentication token (`authToken`) for secure access, provided as `random-token`.

- **Steps to Use:**
  1. Replace `"libsql://database-org.turso.io"` with the actual database name or path you intend to connect to.
  2. Ensure you have the correct authentication token (`authToken`) for accessing the database securely.
  3. Use this connection object (`$db`) to interact with the specified remote database in your PHP application.

**Option 2: Standard DSN Connection with 'https://' Protocol:**

```php
$db = new LibSQL("libsql:dbname=https://database-org.turso.io;authToken=random-token");
```

- **Usage Explanation:**
  - Similar to Option 1, this option utilizes a DSN string with the `https://` protocol to establish a connection.
  - The DSN string specifies the database name or path as `https://database-org.turso.io`.
  - It also requires an authentication token (`authToken`) provided as `random-token` for secure access.

- **Steps to Use:**
  1. Replace `"https://database-org.turso.io"` with the actual database name or path you wish to connect to.
  2. Ensure you have the correct authentication token (`authToken`) for accessing the database securely.
  3. Utilize the created connection object (`$db`) to interact with the designated remote database within your PHP application.

By following the steps outlined above, you can seamlessly establish remote connections to databases using either the `libsql://` or `https://` protocols with the LibSQL PHP Extension. Ensure accurate configuration of the DSN string and authentication token for successful connection establishment and secure data access.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - [In-Memory Connection](002-memory-connection.md)
    - **[Remote Connection](003-remote-connection.md)**
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
- [LibSQLStatement](015-LibSQLStatement.md)
- [LibSQLTransaction](016-LibSQLTransaction.md)
