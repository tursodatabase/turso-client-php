<?php

namespace Darkterminal\LibSQLDriver\Facades;

use Darkterminal\LibSQLDriver\Database\LibSQLDatabase;
use Illuminate\Support\Facades\Facade;

/**
 * @see \Darkterminal\LibSQLDriver\LibSQLDriver
 *
 * @mixin \Darkterminal\LibSQLDriver\LibSQLManager
 * @mixin \Darkterminal\LibSQLDriver\Database\LibSQLDatabase
 */
class LibSQLPHP extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LibSQLDatabase::class;
    }
}
