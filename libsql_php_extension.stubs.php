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
         * @return array The result set.
         */
        public function query(array $parameters)
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
         * The connection ID associated with the transaction.
         * @var string
         */
        public $conn_id;

        /**
         * The transaction ID.
         * @var string
         */
        public $trx_id;

        /**
         * The behavior of the transaction.
         * @var string
         */
        public $trx_behavior;

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
        public function exec(string $stmt, array $parameters = [])
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
         * The connection identifier.
         * @var string
         */
        public $conn_id;

        /**
         * The mode of the connection.
         * @var string
         */
        public $mode;

        /**
         * Creates a new LibSQL instance.
         *
         * @param array $config Configuration options for the database connection.
         */
        public function __construct(array $config)
        {
        }

        /**
         * Retrieves the version of the LibSQL library.
         *
         * @return string The version string.
         */
        public static function version()
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
         * Checks if autocommit mode is enabled for the connection.
         *
         * @return bool True if autocommit is enabled, otherwise false.
         */
        public function isAutocommit()
        {
        }

        /**
         * Executes an SQL statement on the database.
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
         * @param string $stmt The SQL query to execute.
         * @param array $parameters The parameters for the query (optional).
         *
         * @return array The result of the query.
         */
        public function query(string $stmt, array $parameters = [])
        {
        }

        /**
         * Initiates a new database transaction.
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
         * @return void The result of the close operation.
         */
        public function close()
        {
        }
    }
}
