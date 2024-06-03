<?php

declare(strict_types=1);

namespace Turso\Doctrine\DBAL;

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
            switch ($this->getConnectionMode($params)) {
                case 'remote_replica':
                    $defaultParams = [
                        "sync_interval"     => 5,
                        "read_your_writes"  => true,
                        "encryption_key"    => ""
                    ];

                    $config = \array_merge($params, $defaultParams);
                    $this->connection = new LibSQL($config);
                    break;
                case 'remote':
                    $this->connection = new LibSQL("libsql:dbname={$params['sync_url']};authToken={$params['auth_token']}");
                    break;
                case 'local':
                    $encryption_key = !empty($params['encryption_key']) ? $params['encryption_key'] : "";
                    $this->connection = new LibSQL("libsql:dbname=database.db", LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, $encryption_key);
                    break;
                case 'memory':
                    $this->connection = new LibSQL(":memory:");
                    break;

                default:
                    throw new \Exception("Connection mode is not found");
                    break;
            }
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        return new Connection($this->connection);
    }

    private function getConnectionMode($params): string
    {
        if (
            (isset($params['url']) && $this->in_strpos($params['url'], ['.db', '.sqlite']) !== false) &&
            !empty($params['auth_token']) &&
            !empty($params['sync_url'])
        ) {
            return "remote_replica";
        } else if (
            !empty($params['auth_token']) &&
            !empty($params['sync_url'])
        ) {
            return "remote";
        } else if ($this->in_strpos($params['url'], ['.db', '.sqlite']) !== false) {
            return "local";
        } else {
            return "memory";
        }
    }

    private function in_strpos(string $haystack, array $needle): bool
    {
        foreach ($needle as $substring) {
            if (strpos($haystack, $substring) !== false) {
                return true;
            }
        }
        return false;
    }
}
