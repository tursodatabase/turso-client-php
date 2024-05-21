<?php

namespace Darkterminal\LibSQLDriver\Database;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Exception;

class LibSQLConnection extends Connection
{
    public function __construct(LibSQLDatabase $db, string $database = ':memory:', string $tablePrefix = '', array $config = [])
    {
        parent::__construct($db, $database, $tablePrefix, $config);

        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    public function createReadPdo(array $config): ?LibSQLDatabase
    {
        $db = new LibSQLDatabase($config);
        $this->setReadPdo($db);
        return $db;
    }

    protected function escapeBinary(mixed $value): string
    {
        $hex = bin2hex($value);

        return "x'{$hex}'";
    }

    protected function getDefaultPostProcessor(): LibSQLQueryProcessor
    {
        return new LibSQLQueryProcessor();
    }

    public function getSchemaBuilder(): LibSQLSchemaBuilder
    {
        if (is_null($this->schemaGrammar)) {
            $this->useDefaultSchemaGrammar();
        }

        return new LibSQLSchemaBuilder($this);
    }

    public function getSchemaState(?Filesystem $files = null, ?callable $processFactory = null): LibSQLSchemaState
    {
        return new LibSQLSchemaState($this, $files, $processFactory);
    }

    protected function isUniqueConstraintError(Exception $exception): bool
    {
        return boolval(preg_match('#(column(s)? .* (is|are) not unique|UNIQUE constraint failed: .*)#i', $exception->getMessage()));
    }
}
