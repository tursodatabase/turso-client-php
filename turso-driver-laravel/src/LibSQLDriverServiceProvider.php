<?php

namespace Darkterminal\LibSQLDriver;

use Darkterminal\LibSQLDriver\Database\LibSQLConnection;
use Darkterminal\LibSQLDriver\Database\LibSQLConnector;
// use Darkterminal\LibSQLDriver\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Connection;
use Darkterminal\LibSQLDriver\Database\LibSQLConnectionFactory;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\LaravelPackageTools\Package;

class LibSQLDriverServiceProvider extends PackageServiceProvider
{

    public function boot(): void
    {
        if (config('database.default') !== 'libsql' || config('database.connections.libsql.driver') === 'libsql') {
            return;
        }

        $this->app->scoped(LibSQLManager::class, function () {
            return new LibSQLManager(config('database.connections.libsql'));
        });

        $this->app->extend(DatabaseManager::class, function (DatabaseManager $manager) {
            Connection::resolverFor('libsql', function ($connection = null, ?string $database = null, string $prefix = '', array $config = []) {
                $config = config('database.connections.libsql');
                if (!isset($config['driver'])) {
                    $config['driver'] = 'libsql';
                }

                $connector = new LibSQLConnector();
                $pdo = $connector->connect($config);

                $connection = new LibSQLConnection($pdo, $database ?? 'libsql', $prefix, $config);
                app()->instance(LibSQLConnection::class, $connection);

                $connection->createReadPdo($config);

                return $connection;
            });

            return $manager;
        });
    }

    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('libsql-driver-laravel');
    }

    public function register(): void
    {
        $this->app->singleton('db.factory', function ($app) {
            return new LibSQLConnectionFactory($app);
        });
    }
}
