# Configuration

This PHP code snippet demonstrates various configurations for establishing connections with databases using the LibSQL PHP Extension. Let's break down each configuration option:

**Local Connection:**

- **Option 1: Standard DSN Connection:**
  - This option utilizes a Data Source Name (DSN) string to specify the database location. 
  - The DSN format is `libsql:dbname=database.db`.
  - Additional parameters include connection flags and an optional encryption key.

- **Option 2: Standard SQLite Connection:**
  - In this setup, the database filename alone is provided, without a DSN.
  - The database file name is directly specified, e.g., `"database.db"`.
  - Similar to the DSN connection, it also allows for setting connection flags and an optional encryption key.

- **Option 3: Standard LibSQL Connection:**
  - This option resembles the DSN connection but uses the file protocol in the DSN string.
  - The DSN string format is `"file:database.db"`.
  - Connection flags and an encryption key can also be specified.

**Remote Connection:**

- **Option 1: Standard DSN Connection with 'libsql://':**
  - For remote connections, this option utilizes the 'libsql://' protocol in the DSN string.
  - The format is `"libsql:dbname=libsql://database-org.turso.io;authToken=random-token"`.
  - It provides a straightforward approach to connect to remote databases.

- **Option 2: Standard DSN Connection with 'https://':**
  - Similarly, this option connects to remote databases but employs the 'https://' protocol in the DSN string.
  - The format is `"libsql:dbname=https://database-org.turso.io;authToken=random-token"`.
  - It offers an alternative method for establishing connections to remote databases.

**Remote Replica Connection:**

- This configuration is designed for synchronizing and replicating data in distributed environments.
- It requires an array configuration with the following key-value pairs:
  - **url:** Specifies the URL of the remote database.
  - **authToken:** Authentication token for secure access.
  - **syncUrl:** URL for synchronization purposes.
  - **syncInterval:** Integer value representing synchronization interval in seconds (optional, default: 5).
  - **read_your_writes:** Boolean value indicating whether to read your writes (optional, default: true).
  - **encryptionKey:** String value for encryption purposes (optional, default: empty).

By employing these configurations, you can seamlessly establish connections with both local and remote databases, as well as facilitate data synchronization and replication in distributed environments using the LibSQL PHP Extension.
