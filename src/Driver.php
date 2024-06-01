<?php

declare(strict_types=1);

namespace Darkterminal\LibSQL\DBAL;

use Doctrine\DBAL\Driver\AbstractSQLiteDriver;
use LibSQL;
use SensitiveParameter;

final class Driver extends AbstractSQLiteDriver
{
    private LibSQL $connection;

    public function connect(
        #[SensitiveParameter]
        array $params,
    ): Connection {

        try {

            if (
                strpos($params['url'], '.db') !== false &&
                !empty($params['auth_token']) &&
                !empty($params['sync_url']) &&
                (!empty($params['remote_only']) && $params['remote_only'] === false)
            ) {

                $defaultParams = [
                    "sync_interval"     => 5,
                    "read_your_writes"  => true,
                    "encryption_key"    => "",
                    "remote_only"       => false
                ];

                $config = \array_merge($params, $defaultParams);
                $this->connection = new LibSQL($config);

            } else if (
                strpos($params['url'], '.db') !== false &&
                !empty($params['auth_token']) &&
                !empty($params['sync_url']) &&
                (!empty($params['remote_only']) && $params['remote_only'] === true)
            ) {

                $this->connection = new LibSQL("libsql:dbname={$params['sync_url']};authToken={$params['auth_token']}");

            } else if (strpos($params['url'], '.db') !== false) {

                $encryption_key = !empty($params['encryption_key']) ? $params['encryption_key'] : "";
                $this->connection = new LibSQL("libsql:dbname=database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, $encryption_key);

            } else {
                $this->connection = new LibSQL(":memory:");
            }

        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        return new Connection($this->connection);
    }
}
