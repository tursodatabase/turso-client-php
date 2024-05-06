**Remote Replica Connection Configuration Guide:**

Configuring a remote replica connection using the LibSQL PHP Extension, you'll need to provide an array configuration containing specific parameters. Below is a detailed explanation of each parameter and how to use them effectively:

**Array Configuration Parameters:**

1. **URL (Required):**
   - Specifies the URL of the remote database.
   - Example: `"file:database.db"`

2. **AuthToken (Required):**
   - Authentication token for secure access to the remote database.
   - Example: `"secrettoken"`

3. **SyncUrl (Required):**
   - URL for synchronization purposes, typically using the `libsql://` protocol.
   - Example: `"libsql://database-org.turso.io"`

4. **SyncInterval (Optional):**
   - Integer value representing the synchronization interval in seconds.
   - Default: `5` (if not specified)
   - Example: `5`

5. **Read Your Writes (Optional):**
   - Boolean value indicating whether to read your writes.
   - Default: `true` (if not specified)
   - Example: `true`

6. **EncryptionKey (Optional):**
   - String value for encryption purposes, if encryption is required.
   - Default: `""` (empty string if not specified)
   - Example: `""` (no encryption)

**Usage Example:**

```php
$config = [
    "url" => "file:database.db",
    "authToken" => "secrettoken",
    "syncUrl" => "libsql://database-org.turso.io",
    "syncInterval" => 5,
    "read_your_writes" => true,
    "encryptionKey" => "",
];
$db = new LibSQL($config);
```

**Explanation:**
- In the `$config` array, specify the URL of the remote database, authentication token, synchronization URL, synchronization interval (if needed), whether to enable "read your writes," and the encryption key (if encryption is required).
- Pass this configuration array when creating a new `LibSQL` object to establish the remote replica connection.

By correctly configuring the array parameters with the required values, you can establish a remote replica connection using the LibSQL PHP Extension. This allows for synchronization and replication of data across distributed environments, ensuring data integrity and availability.

## Read More

- [Quickstart Guide](quick-start.md)
- [LibSQL Configuration Options](000-configuration.md)
    - [Local Connection](001-local-connection.md)
    - [In-Memory Connection](002-memory-connection.md)
    - [Remote Connection](003-remote-connection.md)
    - **[Remote Replica Connection](004-remote-replica-connection.md)**
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
