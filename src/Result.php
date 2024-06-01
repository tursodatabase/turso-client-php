<?php

declare(strict_types=1);

namespace Darkterminal\LibSQL\DBAL;

use Doctrine\DBAL\Driver\Result as ResultInterface;
use Doctrine\DBAL\Exception\NoKeyValue;

final class Result implements ResultInterface
{
    private array $result = [];

    public function __construct(array $result)
    {
        $this->result = $result;
    }

    public function fetchNumeric(): array|false
    {
        if (empty($this->result['rows'])) {
            return false;
        }

        $result = array_map(function ($row) {
            return array_values($row);
        }, $this->result['rows']);

        return $result;
    }

    public function fetchAssociative(): array|false
    {
        if (empty($this->result['rows'])) {
            return false;
        }

        $columns = array_keys($this->result['columns']);
        $result = array_map(function ($row) use ($columns) {
            return array_combine($columns, $row);
        }, $this->result['rows']);

        return $result;
    }

    public function fetchOne(): mixed
    {
        $row = $this->fetchNumeric();

        if ($row === false) {
            return false;
        }

        return $row[0];
    }

    public function fetchAllNumeric(): array
    {
        if ($this->fetchNumeric() === false) {
            return [];
        }

        return array_map(function ($row) {
            return $row;
        }, $this->fetchNumeric());
    }

    public function fetchAllAssociative(): array
    {
        if ($this->fetchAssociative() === false) {
            return [];
        }

        return array_map(function ($row) {
            return $row;
        }, $this->fetchAssociative());
    }

    public function fetchAllKeyValue(): array
    {
        $this->ensureHasKeyValue();

        $data = [];

        foreach ($this->fetchAllNumeric() as $row) {
            assert(count($row) >= 2);
            [$key, $value] = $row;
            $data[$key]    = $value;
        }

        return $data;
    }

    public function fetchAllAssociativeIndexed(): array
    {
        $data = [];

        foreach ($this->fetchAllAssociative() as $row) {
            $data[array_shift($row)] = $row;
        }

        return $data;
    }

    public function fetchFirstColumn(): array
    {
        return array_map(function($row) {
            return $row;
        }, $this->fetchOne());
    }

    public function rowCount(): int
    {
        return count($this->result['rows']);
    }

    public function columnCount(): int
    {
        return count($this->result['columns']);
    }

    public function free(): void
    {
        $this->result = [];
    }

    private function ensureHasKeyValue(): void
    {
        $columnCount = $this->columnCount();

        if ($columnCount < 2) {
            throw NoKeyValue::fromColumnCount($columnCount);
        }
    }
}
