<?php

declare(strict_types=1);

namespace Darkterminal\LibSQL\DBAL;

use Doctrine\DBAL\Driver\Statement as StatementInterface;
use Doctrine\DBAL\ParameterType;
use LibSQL;
use LibSQLStatement;

final class Statement implements StatementInterface
{
    private const TYPE_BLOB    = SQLITE3_BLOB;
    private const TYPE_INTEGER = SQLITE3_INTEGER;
    private const TYPE_NULL    = SQLITE3_NULL;
    private const TYPE_TEXT    = SQLITE3_TEXT;
    private const TYPE_FLOAT   = SQLITE3_FLOAT;

    protected array $named_parameters = [];
    protected array $positonal_parameters = [];

    /** @internal The statement can be only instantiated by its driver connection. */
    public function __construct(
        private readonly LibSQL $connection,
        private readonly LibSQLStatement $statement,
        private readonly string $sql
    ) {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type): void
    {
        if (!preg_match('/^[:@]/', $param)) {
            if (!preg_match('/^[:@]/', $this->sql)) {
                $this->positonal_parameters[$param] = ['value' => $value, 'type' => $type];
            } else {
                throw new \Exception("Named parameters are not supported for positional queries");
            }
        } else {
            $this->named_parameters[$param] = ['value' => $value, 'type' => $type];
        }
    }

    public function execute(): Result
    {
        try {
            $query = $this->sql;

            if (!empty($this->named_parameters)) {
                foreach ($this->named_parameters as $param => $paramData) {
                    $value = $this->typed_value($paramData['value'], $paramData['type']);
                    if ($paramData['type'] === Statement::TYPE_TEXT || $paramData['type'] === Statement::TYPE_BLOB) {
                        $value = "'" . Connection::escapeString($value) . "'";
                    }
                    $query = str_replace($param, $value, $query);
                }
            }

            if (!empty($this->positonal_parameters)) {
                foreach ($this->positonal_parameters as $param => $paramData) {
                    $value = $this->typed_value($paramData['value'], $paramData['type']);
                    if ($paramData['type'] === Statement::TYPE_TEXT || $paramData['type'] === Statement::TYPE_BLOB) {
                        $value = "'" . Connection::escapeString($value) . "'";
                    }
                    $query = preg_replace('/\?/', $value, $query, 1);
                }
            }

            $this->reset();
        } catch (\Exception $e) {
            throw Exception::new($e);
        }

        $result = $this->connection->query($query);

        return new Result($result, $this->connection->changes());
    }

    public function reset(): void
    {
        $this->named_parameters = [];
        $this->positonal_parameters = [];
    }

    private function typed_value($value, $type): mixed
    {
        switch ($type) {
            case Statement::TYPE_INTEGER:
                $value = intval($value);
                break;
            case Statement::TYPE_FLOAT:
                $value = floatval($value);
                break;
            case Statement::TYPE_TEXT:
                $value = (string) $value;
                break;
            case Statement::TYPE_NULL:
                $value = null;
                break;
            case Statement::TYPE_BLOB:
                $value;
                break;
        }

        return $value;
    }
}
