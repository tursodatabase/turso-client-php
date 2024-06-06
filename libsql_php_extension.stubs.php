<?php

// Stubs for libsql_php_extension

namespace {

    /**
     * Represents a prepared SQL statement.
     */
    class LibSQLStatement
    {
        /**
         * Creates a new LibSQLStatement instance.
         *
         * @param string $conn_id The connection ID.
         * @param string $sql The SQL statement.
         */
        public function __construct(string $conn_id, string $sql)
        {
        }

        /**
         * Finalizes the prepared statement.
         *
         * @return void
         */
        public function finalize()
        {
        }

        /**
         * Executes the prepared statement with given parameters.
         *
         * @param array $parameters The parameters for the statement.
         * 
         * @return int The number of affected rows.
         */
        public function execute(array $parameters)
        {
        }

        /**
         * Executes the prepared statement and retrieves the result set.
         *
         * @param array $parameters The parameters for the statement.
         * 
         * @return LibSQLResult The result set.
         */
        public function query(array $parameters = [])
        {
        }

        /**
         * Resets the prepared statement.
         *
         * @return void
         */
        public function reset()
        {
        }

        /**
         * Gets the number of parameters in the prepared statement.
         *
         * @return int The number of parameters.
         */
        public function parameterCount()
        {
        }

        /**
         * Gets the name of a parameter by index.
         *
         * @param int $idx The index of the parameter.
         * 
         * @return string The name of the parameter.
         */
        public function parameterName(int $idx)
        {
        }

        /**
         * Gets the column names of the result set.
         *
         * @return array The column names.
         */
        public function columns()
        {
        }
    }

    /**
     * Represents a database transaction in LibSQL.
     */
    class LibSQLTransaction
    {
        /**
         * Creates a new LibSQLTransaction instance.
         *
         * @param string $conn_id The connection ID.
         * @param string $trx_mode The transaction mode.
         */
        public function __construct(string $conn_id, string $trx_mode)
        {
        }

        /**
         * Retrieves the number of rows changed by the last SQL statement.
         *
         * @return int The number of rows changed.
         */
        public function changes()
        {
        }

        /**
         * Checks if the transaction is set to autocommit.
         *
         * @return bool True if autocommit is enabled, otherwise false.
         */
        public function isAutocommit()
        {
        }

        /**
         * Executes an SQL statement within the transaction.
         *
         * @param string $stmt The SQL statement to execute.
         * @param array $parameters The parameters for the statement (optional).
         *
         * @return int The number of affected rows.
         */
        public function execute(string $stmt, array $parameters = [])
        {
        }

        /**
         * Executes a query within the transaction and returns the result set.
         *
         * @param string $stmt The SQL statement to execute.
         * @param array $parameters The parameters for the statement (optional).
         *
         * @return array The result set.
         */
        public function query(string $stmt, array $parameters = [])
        {
        }

        /**
         * Commits the transaction.
         *
         * @return void
         */
        public function commit()
        {
        }

        /**
         * Rolls back the transaction.
         *
         * @return void
         */
        public function rollback()
        {
        }
    }

    /**
     * Represents the result of a LibSQL query.
     */
    class LibSQLResult
    {
        /**
         * Creates a new LibSQLResult instance.
         *
         * @param string $config The configuration string for the database connection.
         * @param string $sql The SQL query that produced this result.
         * @param array $parameters The parameters for the SQL query (optional).
         */
        public function __construct(string $config, string $sql, array $parameters = [])
        {
        }

        /**
         * Fetches the result set as an array.
         *
         * @param int $mode The fetching mode (optional, default is 3).
         *
         * @return array The fetched result set.
         */
        public function fetchArray(int $mode = 3)
        {
        }

        /**
         * Finalizes the result set and frees the associated resources.
         *
         * @return void
         */
        public function finalize()
        {
        }

        /**
         * Resets the result set for re-execution.
         *
         * @return void
         */
        public function reset()
        {
        }

        /**
         * Retrieves the name of a column by its index.
         *
         * @param int $column The index of the column.
         *
         * @return string The name of the column.
         */
        public function columnName(int $column)
        {
        }

        /**
         * Retrieves the type of a column by its index.
         *
         * @param int $column The index of the column.
         *
         * @return string The type of the column.
         */
        public function columnType(int $column)
        {
        }

        /**
         * Retrieves the number of columns in the result set.
         *
         * @return int The number of columns.
         */
        public function numColumns()
        {
        }
    }


    /**
     * Represents a connection to a LibSQL database.
     */
    class LibSQL
    {
        /**
         * Specifies read-only mode when opening the database connection.
         */
        const OPEN_READONLY = 1;

        /**
         * Specifies read-write mode when opening the database connection.
         */
        const OPEN_READWRITE = 2;

        /**
         * Specifies create mode when opening the database connection.
         */
        const OPEN_CREATE = 4;

        /**
         * Return associative array.
         */
        const LIBSQL_ASSOC = 1;

        /**
         * Return numerical array
         */
        const LIBSQL_NUM = 2;

        /**
         * Return both associative and numerical array
         */
        const LIBSQL_BOTH = 3;

        /**
         * Return a result sets
         */
        const LIBSQL_ALL = 4;

        /**
         * The mode of the connection.
         * @var string
         */
        public $mode;

        /**
         * Creates a new LibSQL instance.
         * 
         * ## Example Usage
         * 1. **Local Connection:**
         * 
         *    Establishing a connection to a local database is straightforward with LibSQL. You have three options:
         * 
         *    a. **Standard DSN Connection:** If you're using a DSN string, use the following format:
         *       ```
         *       $db = new LibSQL("libsql:dbname=database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
         *       ```
         *       
         *    b. **Standard SQLite Connection:** For direct SQLite connections, simply provide the database file name:
         *       ```
         *       $db = new LibSQL("database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
         *       ```
         *       
         *    c. **Standard LibSQL Connection:** Alternatively, you can specify the file protocol explicitly:
         *       ```
         *       $db = new LibSQL("file:database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, "");
         *       ```
         * 
         * 2. **Remote Connection:**
         * 
         *    Connecting to a remote database is equally effortless. Choose between two options:
         * 
         *    a. **Standard DSN Connection with 'libsql://':**
         *       ```
         *       $db = new LibSQL("libsql:dbname=libsql://database-org.turso.io;authToken=random-token");
         *       ```
         *       
         *    b. **Standard DSN Connection with 'https://':**
         *       ```
         *       $db = new LibSQL("libsql:dbname=https://database-org.turso.io;authToken=random-token");
         *       ```
         * 
         * 3. **Remote Replica Connection:**
         * 
         *    To set up a replica connection for distributed systems, follow these steps:
         * 
         *    a. Define the configuration array with the required parameters:
         *       ```
         *       $config = [
         *          "url" => "file:database.db",
         *          "authToken" => "secrettoken",
         *          "syncUrl" => "libsql://database-org.turso.io",
         *          "syncInterval" => 5,
         *          "read_your_writes" => true,
         *          "encryptionKey" => "",
         *       ];
         *       ```
         * 
         *    b. Instantiate a new LibSQL object with the configuration array:
         *       ```
         *       $db = new LibSQL($config);
         *       ```
         * 
         * With this Quick Start guide, you're ready to seamlessly integrate LibSQL PHP Extension into your projects, whether for local, remote, or distributed database connections. 
         *
         * @param string|array $config
         * @param integer|null $flags
         * @param string|null $encryption_key
         */
        public function __construct(string|array $config, ?int $flags = 6, ?string $encryption_key = "")
        {
        }

        /**
         * Retrieves the version of the LibSQL library.
         * 
         * ## Example Usage
         * ```
         * // Retrieve the version of the LibSQL
         * $version = LibSQL::version();
         * echo $version;
         * 
         * // Output
         * // LibSQL Core Version : 3.44.0-3044000 - LibSQL PHP Extension Version: 1.0.0
         * ```
         *
         * @return string The version string.
         */
        public static function version()
        {
        }

        /**
         * Retrieves the number of rows changed by the last SQL statement.
         *
         * ## Example Usage
         * 
         * ```
         * // Create a new LibSQL instance
         * $db = new LibSQL("libsql:dbname=database.db");
         * 
         * $stmt = "UPDATE users SET age = 28 WHERE id = 1";
         * $db->execute($stmt);
         * 
         * // Retrieve the number of rows changed
         * $changes = $db->changes();
         * echo "Number of Rows Changed: " . $changes;
         * 
         * $db->close();
         * ```
         * @return int The number of rows changed.
         */
        public function changes()
        {
        }

        /**
         * Checks if autocommit mode is enabled for the connection.
         * 
         * ## Example Usage
         * 
         * ```
         * // Create a new LibSQL instance
         * $db = new LibSQL("libsql:dbname=database.db");
         * 
         * // Check if autocommit mode is enabled
         * if ($db->isAutocommit()) {
         *     echo "Autocommit mode is ENABLED." . PHP_EOL;
         * } else {
         *     echo "Autocommit mode is DISABLED." . PHP_EOL;
         * }
         * $db->close();
         * ```
         *
         * @return bool True if autocommit is enabled, otherwise false.
         */
        public function isAutocommit()
        {
        }

        /**
         * Executes an SQL statement on the database.
         * 
         * ## Example Usage
         * 
         * ```
         * // SQL statement with positional parameters
         * $stmt = "INSERT INTO users (name, age) VALUES (?, ?)";
         * $parameters = ["John Doe", 30];
         * $rowsAffected = $db->execute($stmt, $parameters);
         * echo "Inserted $rowsAffected rows." . PHP_EOL;
         * 
         * // SQL statement with named parameters
         * $stmt = "UPDATE users SET name = :name WHERE id = :id";
         * $parameters = [":name" => "Jane Doe", ":id" => 6];
         * $rowsAffected = $db->execute($stmt, $parameters);
         * echo "Updated $rowsAffected rows." . PHP_EOL;
         * ```
         *
         * @param string $stmt The SQL statement to execute.
         * @param array $parameters The parameters for the statement (optional).
         *
         * @return int The number of rows affected by the statement.
         */
        public function execute(string $stmt, array $parameters = [])
        {
        }

        /**
         * Executes a batch of SQL statements on the database.
         * 
         * ## Example Usage
         * 
         * ```
         * // Create a new LibSQL instance
         * $db = new LibSQL("libsql:dbname=database.db");
         * 
         * // SQL statements to execute as a batch
         * $stmt = "
         *     INSERT INTO users (name, age) VALUES ('Jane Jenifer', 30);
         *     INSERT INTO users (name, age) VALUES ('Jane Smith', 25);
         *     INSERT INTO users (name, age) VALUES ('Michael Johnson', 40);
         * ";
         * 
         * // Execute the batch of SQL statements
         * if ($db->executeBatch($stmt)) {
         *     echo "Batch execution successful.";
         * } else {
         *     echo "Batch execution failed.";
         * }
         * 
         * $db->close();
         * ```
         *
         * @param string $stmt The SQL statements to execute as a batch.
         *
         * @return bool True if the batch execution was successful, otherwise false.
         */
        public function executeBatch(string $stmt)
        {
        }

        /**
         * Executes an SQL query on the database.
         * 
         * ## Example Usage
         * 
         * ```
         * $db = new LibSQL("libsql:dbname=database.db");
         * 
         * $results = $db->query("SELECT * FROM users");
         * 
         * foreach ($results['rows'] as $row) {
         *     echo "ID: " . $row['id'] . ", Name: " . $row['name'] . ", Age: " . $row['age'] . "\n";
         * }
         * 
         * $db->close();
         * ```
         *
         * @param string $stmt The SQL query to execute.
         * @param array $parameters The parameters for the query (optional).
         *
         * @return LibSQLResult The result of the query.
         */
        public function query(string $stmt, array $parameters = [])
        {
        }

        /**
         * Initiates a new database transaction.
         * 
         * ## Example Usage
         * 
         * ```
         * // Create a new LibSQL instance
         * $db = new LibSQL("libsql:dbname=database.db");
         * 
         * // Start a new transaction with default behavior
         * $transaction = $db->transaction();
         * 
         * $transaction->execute("UPDATE users SET name = 'Glauber Costa' WHERE id = 6");
         * 
         * $another_transaction = true;
         * 
         * if ($another_transaction) {
         *     $transaction->commit();
         *     echo "Transaction commited!" . PHP_EOL;
         * } else {
         *     $transaction->rollback();
         *     echo "Transaction rollback!" . PHP_EOL;
         * }
         * 
         * $db->close();
         * ```
         *
         * @param string $behavior The behavior of the transaction (optional).
         *
         * @return \LibSQLTransaction The transaction object.
         */
        public function transaction(string $behavior = "DEFERRED")
        {
        }

        /**
         * Prepares an SQL statement for execution.
         *
         * ## Example Usage
         * 
         * ```
         * // Create a new LibSQL instance
         * $db = new LibSQL("libsql:dbname=database.db");
         * 
         * // Prepare an SQL statement for execution
         * $sql = "SELECT * FROM users WHERE id = ?";
         * $statement = $db->prepare($sql);
         * 
         * if ($statement) {
         *     // Execute the prepared statement with parameters
         *     $result = $statement->query([1]);
         *     var_dump($result);
         * } else {
         *     // Handle error
         *     echo "Failed to prepare statement.";
         * }
         * 
         * $db->close();
         * ```
         * 
         * @param string $sql The SQL statement to prepare.
         *
         * @return \LibSQLStatement The prepared statement object.
         */
        public function prepare(string $sql)
        {
        }

        /**
         * Closes the database connection.
         * 
         * # Example Usage
         * 
         * ```
         * $db->close();
         * ```
         *
         * @return void The result of the close operation.
         */
        public function close()
        {
        }

        /**
         * Sync the database.
         * 
         * # Example Usage
         * 
         * ```
         * $db->sync();
         * ```
         *
         * @return void The result of the sync operation.
         */
        public function sync()
        {
        }
    }
}
