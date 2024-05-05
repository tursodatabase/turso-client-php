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
