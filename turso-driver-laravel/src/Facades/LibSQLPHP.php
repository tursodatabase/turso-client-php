<?php

namespace Turso\Driver\Laravel\Facades;

use Turso\Driver\Laravel\Database\LibSQLDatabase;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Turso\Driver\Laravel\LibSQLDriver
 *
 * @mixin \Turso\Driver\Laravel\LibSQLManager
 * @mixin \Turso\Driver\Laravel\Database\LibSQLDatabase
 */
class LibSQLPHP extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LibSQLDatabase::class;
    }
}
