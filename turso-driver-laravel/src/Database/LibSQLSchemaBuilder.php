<?php

declare(strict_types=1);

namespace Turso\Driver\Laravel\Database;

use Illuminate\Database\Schema\SQLiteBuilder;
use Darkterminal\LibSQL\Exceptions\FeatureNotSupportedException;

class LibSQLSchemaBuilder extends SQLiteBuilder
{
    public function createDatabase($name)
    {
        throw new FeatureNotSupportedException('Creating database is not supported in LibSQL database.');
    }

    public function dropDatabaseIfExists($name)
    {
        throw new FeatureNotSupportedException('Dropping database is not supported in LibSQL database.');
    }

    protected function dropAllIndexes(): void
    {
        $statement = $this->connection->getPdo()->query($this->grammar()->compileDropAllIndexes());

        collect($statement['rows'])->each(function (array $query) {
            $this->connection->select($query[0]);
        });
    }

    public function dropAllTables(): void
    {
        $this->dropAllTriggers();
        $this->dropAllIndexes();

        $this->connection->select($this->grammar()->compileDisableForeignKeyConstraints());

        $statement = $this->connection->getPdo()->query($this->grammar()->compileDropAllTables());

        collect($statement['rows'])->each(function (array $query) {
            $this->connection->select($query[0]);
        });

        $this->connection->select($this->grammar()->compileEnableForeignKeyConstraints());
    }

    protected function dropAllTriggers(): void
    {
        $statement = $this->connection->getPdo()->query($this->grammar()->compileDropAllTriggers());

        collect($statement['rows'])->each(function (array $query) {
            $this->connection->select($query[0]);
        });
    }

    public function dropAllViews(): void
    {
        $statement = $this->connection->getPdo()->prepare($this->grammar()->compileDropAllViews());

        collect($statement['rows'])->each(function (array $query) {
            $this->connection->select($query[0]);
        });
    }

    protected function grammar(): LibSQLSchemaGrammar
    {
        if (!($this->grammar instanceof LibSQLSchemaGrammar)) {
            $this->grammar = new LibSQLSchemaGrammar();
        }

        return $this->grammar;
    }
}
