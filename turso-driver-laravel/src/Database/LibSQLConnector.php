<?php

namespace Darkterminal\LibSQLDriver\Database;

use Illuminate\Database\Connectors\Connector;
use Illuminate\Database\Connectors\ConnectorInterface;

class LibSQLConnector
{
    /**
     * Establish a database connection.
     *
     * @return \Darkterminal\LibSQLDriver\Database\LibSQLDatabase
     */
    public function connect(array $config): LibSQLDatabase
    {
        return new LibSQLDatabase($config);
    }
}
