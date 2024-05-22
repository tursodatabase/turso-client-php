<?php

namespace Darkterminal\LibSQLDriver\Database;

use Darkterminal\LibSQLDriver\Exceptions\ConfigurationIsNotFound;
use LibSQL;

class LibSQLDatabase
{
    protected string $connection_mode;
    protected LibSQL $db;
    protected array $config;
    protected array $lastInsertIds = [];

    public function __construct(array $config = [])
    {
        $config = config('database.connections.libsql');
        $libsql = $this->checkConnectionMode($config['url'], $config['syncUrl'], $config['authToken']);
        if ($this->connection_mode === 'local') {
            $this->db = new LibSQL("file:" . $libsql['uri'], LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE, $config['encryptionKey']);
        } else if ($this->connection_mode === 'memory') {
            $this->db = new LibSQL($libsql['uri']);
        } else if ($this->connection_mode === 'remote') {
            $this->db = new LibSQL("libsql:dbname={$libsql['url']};authToken={$libsql['token']}");
        } else if ($this->connection_mode === 'remote_replica') {
            $removeKeys = ['driver', 'name', 'prefix', 'name', 'database'];
            foreach ($removeKeys as $key) {
                unset($config[$key]);
            }
            $this->db = new LibSQL($config);
        } else {
            throw new ConfigurationIsNotFound("Connection not found!");
        }
    }

    public function prepare(string $sql): LibSQLPDOStatement
    {
        return new LibSQLPDOStatement($this->db, $sql);
    }

    public function setLastInsertId(?string $name = null, ?int $value = null): void
    {
        if ($name === null) {
            $name = 'id';
        }

        $this->lastInsertIds[$name] = $value;
    }

    /**
     * Check the connection mode based on the provided path.
     *
     * @param string $path The database connection path.
     *
     * @return array|false The connection mode details, or false if not applicable.
     */
    private function checkConnectionMode(string $path, string $url = "", string $token = ""): array|false
    {
        if (strpos($path, "file:") !== false && $path !== 'file:' && !empty($url) && !empty($token)) {
            $this->connection_mode = 'remote_replica';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => $path,
                'url' => $url,
                'token' => $token
            ];
        } else if ($path === 'file:' && !empty($url) && !empty($token)) {
            $this->connection_mode = 'remote';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => $path,
                'url' => $url,
                'token' => $token
            ];
        } else if (strpos($path, "file:") !== false) {
            $this->connection_mode = 'local';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => str_replace("file:", "", $path)
            ];
        } else if ($path === ":memory:") {
            $this->connection_mode = 'memory';
            $path = [
                'mode' => $this->connection_mode,
                'uri' => $path
            ];
        } else {
            $path = false;
        }

        return $path;
    }
}
