# LibSQL PHP Extension

## Introduction

The LibSQL PHP Extension facilitates connections to LibSQL databases from PHP applications, offering a range of functionalities to streamline database operations. This documentation provides a detailed overview of the LibSQL class and its methods.

## Class Synopsis

```php
class LibSQL
{
   // Constants
   const OPEN_READONLY = 1;
   const OPEN_READWRITE = 2;
   const OPEN_CREATE = 4;
   const LIBSQL_ASSOC = 1;
   const LIBSQL_NUM = 2;
   const LIBSQL_BOTH = 3;
   const LIBSQL_ALL = 4;
   const LIBSQL_LAZY = 5;

   // Properties
   public $mode;

   // Methods
   public function __construct(
      string|array $config,
      ?bool $sqld_offline_mode = false,
      ?int $flags = 6,
      ?string $encryption_key = "",
      ?bool $offline_writes = false
   );
   public static function version(): string;
   public function changes(): int;
   public function isAutocommit(): bool;
   public function totalChanges(): int;
   public function lastInsertedId(): int;
   public function execute(string $stmt, ?array $parameters = []): int;
   public function executeBatch(string $stmt): bool;
   public function query(string $stmt, array $parameters = [], bool $force_remote = false): LibSQLResult;
   public function transaction(?string $behavior = "DEFERRED"): LibSQLTransaction;
   public function prepare(string $sql): LibSQLStatement;
   public function close(): void;
   public function sync(): void;
   public function checkConnectivity(): bool;
   public function getPendingOperationsCount(): int;
   public function isOnline(): bool;
   public function enableLoadExtension(?bool $onoff): void;
   public function loadExtensions(array|string $extension_paths): void;
}
```

## Table of Contents

- [Create a new LibSQL instance](LibSQL-class.md#create-a-new-libsql-instance)
- [Retrieves the version of the LibSQL extension](LibSQL-class.md#retrieves-the-version-of-the-libsql-extension)
- [Retrieves the number of rows changed by the last SQL statement](LibSQL-class.md#retrieves-the-number-of-rows-changed-by-the-last-sql-statement)
- [Checks if autocommit mode is enabled for the connection](LibSQL-class.md#checks-if-autocommit-mode-is-enabled-for-the-connection)
- [Retrieves the number of rows changed by the last SQL statement](LibSQL-class.md#retrieves-the-number-of-rows-changed-by-the-last-sql-statement)
- [Executes an SQL statement on the database](LibSQL-class.md#executes-a-batch-of-sql-statements-on-the-database)
- [Executes a batch of SQL statements on the database](LibSQL-class.md#executes-an-sql-statement-on-the-database)
- [Executes an SQL query on the database](LibSQL-class.md#executes-an-sql-query-on-the-database)
- [Initiates a new database transaction](LibSQL-class.md#initiates-a-new-database-transaction)
- [Prepares an SQL statement for execution](LibSQL-class.md#prepares-an-sql-statement-for-execution)
- [Closes the database connection](LibSQL-class.md#closes-the-database-connection)
- [Sync the database](LibSQL-class.md#sync-the-database)
- [Checks the connectivity of the database server (self-host libsql-server)](LibSQL-class.md#checks-the-connectivity-of-the-database-server)
- [Returns the number of pending operations (self-host libsql-server)](LibSQL-class.md#returns-the-number-of-pending-operations)
- [Checks if the database connection is online (self-host libsql-server)](LibSQL-class.md#checks-if-the-database-connection-is-online)
- [Enable or disable the loading of extensions](LibSQL-class.md#enable-or-disable-the-loading-of-extensions)
- [Load (sqlite-compatible) extensions](LibSQL-class.md#load-extensions)

### Create a new LibSQL instance

1. **Local Connection:**

   Establishing a connection to a local database is straightforward with LibSQL. You have three options:

   a. **Standard DSN Connection:** If you're using a DSN string, use the following format:

      ```php
      $db = new LibSQL("libsql:dbname=database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
      ```
      
   b. **Standard SQLite Connection:** For direct SQLite connections, simply provide the database file name:

      ```php
      $db = new LibSQL("database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
      ```
      
   c. **Standard LibSQL Connection:** Alternatively, you can specify the file protocol explicitly:

      ```php
      $db = new LibSQL("file:database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
      ```

2. **Remote Connection:**

   Connecting to a remote database is equally effortless. Choose between two options:

   a. **Standard DSN Connection with 'libsql://':**

      ```php
      $db = new LibSQL("libsql:dbname=libsql://database-org.turso.io;authToken=random-token");
      ```
      
   b. **Standard DSN Connection with 'https://':**

      ```php
      $db = new LibSQL("libsql:dbname=https://database-org.turso.io;authToken=random-token");
      ```

3. **Remote Replica Connection:**

   To set up a replica connection for distributed systems, follow these steps:

   a. Define the configuration array with the required parameters:

      ```php
      $config = [
         "url" => "file:database.db",
         "authToken" => "secrettoken",
         "syncUrl" => "libsql://database-org.turso.io",
         "syncInterval" => 5,
         "read_your_writes" => true,
         "encryptionKey" => ""
      ];

      $db = new LibSQL(
         config: $config,
         sqld_offline_mode: false,
         flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
         encryption_key: "",
         offline_writes: false
      );
      ```
      
   b. To establish a replica connection with offline writes, set the offline_writes parameter to true:

      ```php
      $db = new LibSQL(
         config: $config,
         sqld_offline_mode: false,
         flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
         encryption_key: "",
         offline_writes: true
      );
      ```
      
      Note: The offline_writes parameter is currently in beta and may have some limitations. It's only for Turso Cloud database not Libsql Server (sqld) instance.
      
   c. To establish a replica connection with offline writes for sqld instance, set the offline_writes parameter to true and sqld_offline_mode to true:

      ```php
      $db = new LibSQL(
         config: $config,
         sqld_offline_mode: true,
         flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
         encryption_key: "",
         offline_writes: true
      );
      ```
      
      Note: The offline_writes parameter is currently in beta and may have some limitations. It's only for Libsql Server (sqld) instance.

   d. Instantiate a new LibSQL object with the configuration array:

      ```php
      $db = new LibSQL($config, LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "", false);
      $db = new LibSQL($config, LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "", true); // Offline Write BETA
      ```

With this Quick Start guide, you're ready to seamlessly integrate LibSQL PHP Extension into your projects, whether for local, remote, or distributed database connections.

---

### Retrieves the version of the LibSQL extension

```php
// Retrieve the version of the LibSQL
$version = LibSQL::version();
echo $version;

// Output
// LibSQL Core Version : 3.44.0-3044000 - LibSQL PHP Extension Version: 1.0.0
```

---

### Retrieves the number of rows changed by the last SQL statement

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

$stmt = "UPDATE users SET age = 28 WHERE id = 1";
$db->execute($stmt);

// Retrieve the number of rows changed
$changes = $db->changes();
echo "Number of Rows Changed: " . $changes;

$db->close();
```

---

### Checks if autocommit mode is enabled for the connection

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// Check if autocommit mode is enabled
if ($db->isAutocommit()) {
    echo "Autocommit mode is ENABLED." . PHP_EOL;
} else {
    echo "Autocommit mode is DISABLED." . PHP_EOL;
}
$db->close();
```

---

### Retrieves the number of rows changed by the last SQL statement

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

$stmt = "UPDATE users SET age = 28 WHERE id = 1";
$db->execute($stmt);

// Retrieve the number of rows changed
$changes = $db->totalChanges();
echo "Number of Rows Changed: " . $changes;

$db->close();
```

---

### Retrieves the ID of the last inserted row

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

$stmt = "INSERT INTO users (name, age) VALUES ('John Doe', 30)";
$db->execute($stmt);

// Retrieve the ID of the last inserted row
$id = $db->lastInsertedId();
echo "Last inserted row ID: " . $id;

$db->close();
```

---

### Executes an SQL statement on the database

```php
// SQL statement with positional parameters
$stmt = "INSERT INTO users (name, age) VALUES (?, ?)";
$parameters = ["John Doe", 30];
$rowsAffected = $db->execute($stmt, $parameters);
echo "Inserted $rowsAffected rows." . PHP_EOL;

// SQL statement with named parameters
$stmt = "UPDATE users SET name = :name WHERE id = :id";
$parameters = [":name" => "Jane Doe", ":id" => 6];
$rowsAffected = $db->execute($stmt, $parameters);
echo "Updated $rowsAffected rows." . PHP_EOL;
```

### Executes a batch of SQL statements on the database

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// SQL statements to execute as a batch
$stmt = "
    INSERT INTO users (name, age) VALUES ('Jane Jenifer', 30);
    INSERT INTO users (name, age) VALUES ('Jane Smith', 25);
    INSERT INTO users (name, age) VALUES ('Michael Johnson', 40);
";

// Execute the batch of SQL statements
if ($db->executeBatch($stmt)) {
    echo "Batch execution successful.";
} else {
    echo "Batch execution failed.";
}

$db->close();
```

### Executes an SQL query on the database

```php
$db = new LibSQL("libsql:dbname=database.db");

$results = $db->query("SELECT * FROM users");
$rows = $results->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
    echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . "\n";
}

$db->close();
```

### Initiates a new database transaction

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// Start a new transaction with default behavior
$transaction = $db->transaction();

$transaction->execute("UPDATE users SET name = 'Glauber Costa' WHERE id = 6");

$another_transaction = true;

if ($another_transaction) {
    $transaction->commit();
    echo "Transaction commited!" . PHP_EOL;
} else {
    $transaction->rollback();
    echo "Transaction rollback!" . PHP_EOL;
}

$db->close();
```

### Prepares an SQL statement for execution

```php
// Create a new LibSQL instance
$db = new LibSQL("libsql:dbname=database.db");

// Prepare an SQL statement for execution
$sql = "SELECT * FROM users WHERE id = ?";
$statement = $db->prepare($sql);

if ($statement) {
    // Execute the prepared statement with parameters
    $result = $statement->query([1]);
    var_dump($result->fetchArray());
} else {
    // Handle error
    echo "Failed to prepare statement.";
}

$db->close();
```

### Closes the database connection

```php
$db->close();
```

### Sync the database

```php
$db->sync();
```

_NOTE: Embedded Replica / Offline Writes Only_

### Checks the connectivity of the database server

```php
$db->checkConnectivity();
```

_NOTE: Offline Writes (sqld/libsql-server) Only_

### Returns the number of pending operations

```php
$db->getPendingOperationsCount();
```

_NOTE: Offline Writes (sqld/libsql-server) Only_

### Checks if the database connection is online

```php
$db->isOnline();
```

_NOTE: Offline Writes (sqld/libsql-server) Only_

### Enable or disable the loading of extensions

```php
$db->enableLoadExtension(true); // or false
```

### Load extensions

```php
$db->loadExtensions(["extension1", "extension2"]);
```
