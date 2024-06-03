<?php

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class LibSQLConnector
{
    /**
     * Establish a database connection.
     *
     * @return \Turso\Driver\Laravel\Database\LibSQLDatabase
     */
    public function connect(array $config): LibSQLDatabase
    {
        return new LibSQLDatabase($config);
    }
}
