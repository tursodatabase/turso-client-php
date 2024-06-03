<?php

namespace Turso\Driver\Laravel;

use Turso\Driver\Laravel\Database\LibSQLDatabase;
use Illuminate\Support\Collection;

class LibSQLManager
{
    protected LibSQLDatabase $client;

    protected Collection $config;

    public function __construct(array $config = [])
    {
        $this->config = new Collection($config);
        $this->client = new LibSQLDatabase($config);
    }

    public function __call(string $method, array $arguments = []): mixed
    {
        if (!method_exists($this->client, $method)) {
            throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $method . '()');
        }

        return $this->client->$method(...$arguments);
    }
}
