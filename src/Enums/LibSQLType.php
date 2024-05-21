<?php

namespace Darkterminal\LibSQLDriver\Enums;

enum LibSQLType: string
{
    case NULL = 'null';
    case INTEGER = 'integer';
    case FLOAT = 'float';
    case TEXT = 'text';
    case BLOB = 'blob';

    public static function fromValue(mixed $value): static
    {
        $result = match (gettype($value)) {
            'NULL' => self::NULL,
            'boolean', 'integer' => self::INTEGER,
            'double', 'float' => self::FLOAT,
            'string' => self::fromString($value),

            default => null,
        };

        return ($result !== null)
            ? $result
            : self::TEXT;
    }

    public static function fromString(string $value): self
    {
        if (!ctype_print($value) || !mb_check_encoding($value, 'UTF-8')) {
            return self::BLOB;
        }

        return self::TEXT;
    }

    public function bind(mixed $value): array
    {
        return match ($this) {
            self::NULL => [
                'type'  => $this->value,
                'value' => 'null',
            ],
            self::FLOAT => [
                'type'  => $this->value,
                'value' => $value,
            ],
            self::BLOB => [
                'type'   => $this->value,
                'base64' => base64_encode(base64_encode($value)),
            ],

            default => [
                'type'  => $this->value,
                'value' => (string) $value,
            ],
        };
    }
}
