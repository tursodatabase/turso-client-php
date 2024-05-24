<?php

namespace Darkterminal\LibSQLDriver\Database;

use Darkterminal\LibSQLDriver\Database\LibSQLSchemaGrammar;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Database\Connection;
use Exception;

class LibSQLConnection extends Connection
{
    protected LibSQLDatabase $db;

    public function __construct(LibSQLDatabase $db, string $database = ':memory:', string $tablePrefix = '', array $config = [])
    {
        parent::__construct($db, $database, $tablePrefix, $config);

        $this->db = $db;
        $this->schemaGrammar = $this->getDefaultSchemaGrammar();
    }

    public function sync(): void
    {
        $this->db->sync();
    }

    public function select($query, $bindings = [], $useReadPdo = true)
    {
        // Example method where query execution and fetching might occur
        $result = (array) parent::select($query, $bindings, $useReadPdo);

        // Convert result objects to arrays if they are not already
        $resultArray = array_map(function ($item) {
            return (array) $item;
        }, $result);

        return $resultArray;
    }

    /**
     * Get the default schema grammar instance.
     *
     * @return \Illuminate\Database\Schema\Grammars\Grammar
     */
    protected function getDefaultSchemaGrammar()
    {
        return $this->withTablePrefix(new LibSQLSchemaGrammar);
    }

    // You might already have this method, but ensure it correctly sets the schema grammar
    public function useDefaultSchemaGrammar()
    {
        if (is_null($this->schemaGrammar)) {
            $this->schemaGrammar = $this->getDefaultSchemaGrammar();
        }
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
