<?php

declare(strict_types=1);

namespace Darkterminal\LibSQL\DBAL;

use Doctrine\DBAL\Driver\AbstractException;

final class Exception extends AbstractException
{
    public static function new(\Exception $exception): self
    {
        return new self($exception->getMessage(), null, (int) $exception->getCode(), $exception);
    }
}
