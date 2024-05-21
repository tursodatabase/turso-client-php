<?php

namespace Darkterminal\LibSQLDriver\Database;

use Darkterminal\LibSQLDriver\Enums\LibSQLType;
use Darkterminal\LibSQLDriver\Enums\PdoParam;
use Darkterminal\LibSQLDriver\Facades\LibSQLPHP;
use LibSQL;
use PDO;
use PDOStatement;

class LibSQLPDOStatement extends PDOStatement
{
    protected int $affectedRows = 0;
    protected int $fetchMode = PDO::FETCH_BOTH;
    protected array $bindings = [];
    protected array $response = [];

    public function __construct(
        protected PDO|LibSQL $pdo,
        protected string $query,
        protected array $options = [],
    ) {
    }

    public function setFetchMode(int $mode, mixed ...$args): bool
    {
        $this->fetchMode = $mode;
        return true;
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        $type = LibSQLType::fromValue($value);
        $this->bindings[$param] = $type->bind($value);
        return true;
    }

    public function execute(array $parameters = null): int
    {
        collect((array) $parameters)
            ->each(function (mixed $value, int $key) {
                $type = PdoParam::fromValue($value);
                $this->bindValue($key, $value, $type->value);
            });

        $this->response = LibSQLPHP::query($this->query, array_values($this->bindings));

        $lastId = (int) $this->response['last_insert_rowid'];
        if ($lastId > 0) {
            $this->pdo->setLastInsertId(value: $lastId);
        }

        $this->affectedRows = $this->response['rows_affected'];

        return $this->affectedRows ?? 0;
    }

    public function fetch(
        int $mode = PDO::FETCH_DEFAULT,
        int $cursorOrientation = PDO::FETCH_ORI_NEXT,
        int $cursorOffset = 0
    ): mixed
    {
        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->fetchMode;
        }

        $rows = array_shift($this->response['rows']);
        $rowValues = array_values($rows);

        if ($rows === null) {
            return false;
        }

        return match ($mode) {
            PDO::FETCH_BOTH => array_merge(
                $rows,
                $rowValues
            ),
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $rows,
            PDO::FETCH_NUM => $rowValues,
            PDO::FETCH_OBJ => (object) $rows,

            default => throw new PDOException('Unsupported fetch mode.'),
        };
    }

    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, ...$args): array
    {
        if (!($this->response instanceof QueryResponse)) {
            return [];
        }

        if ($mode === PDO::FETCH_DEFAULT) {
            $mode = $this->fetchMode;
        }

        $allRows = $this->response['rows'];
        $rowValues = array_values($rows);

        $response = match ($mode) {
            PDO::FETCH_BOTH => array_merge($allRows, $rowValues),
            PDO::FETCH_ASSOC, PDO::FETCH_NAMED => $allRows,
            PDO::FETCH_NUM => $rowValues,
            PDO::FETCH_OBJ => (object) $allRows,
            default => throw new PDOException('Unsupported fetch mode.'),
        };

        return $response;
    }

    public function getAffectedRows(): int
    {
        return $this->affectedRows;
    }

    public function nextRowset(): bool
    {
        // TFIDK: database is support for multiple rowset.
        return false;
    }

    public function rowCount(): int
    {
        return max((int) count($this->response['rows']), $this->affectedRows);
    }
}
