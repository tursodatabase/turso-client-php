<?php

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Connectors\ConnectionFactory as BaseConnectionFactory;

class LibSQLConnectionFactory extends BaseConnectionFactory
{
    protected function createConnection($driver, $connection, $database, $prefix = '', array $config = [])
    {
        $config['driver'] = "libsql";
        $config['url'] = "file:". $config['database'];
        $connection = new LibSQLDatabase($config);

        return parent::createConnection($config['driver'], $connection, $config['url'], $prefix, $config);
    }

    public function createConnector(array $config)
    {
        $connector = new LibSQLConnector();
        $connector->connect(config('database.connections.libsql'));

        return parent::createConnector($connector);
    }
}
