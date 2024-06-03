<p align="center">
  <a href="https://github.com/darkterminal/turso-doctrine-dbal">
    <img alt="Turso + TypeScript" src="https://i.imgur.com/r009n2u.png" width="1000">
    <h3 align="center">Turso + Doctrine DBAL</h3>
  </a>
</p>

<p align="center">
  SQLite for Production. Powered by <a href="https://turso.tech/libsql">libSQL</a> and <a href="https://github.com/darkterminal/libsql-extension">libSQL Extension</a> for PHP.
</p>

<p align="center">
  <a href="https://turso.tech"><strong>Turso</strong></a> ·
  <a href="https://darkterminal.mintlify.app/dark-packages/turso-doctrine-dbal/readme"><strong>Quickstart</strong></a> ·
  <a href="https://darkterminal.mintlify.app/dark-packages/turso-doctrine-dbal/readme"><strong>Examples</strong></a> ·
  <a href="https://darkterminal.mintlify.app/dark-packages/turso-doctrine-dbal/readme"><strong>Docs</strong></a> ·
  <a href="https://discord.com/invite/4B5D7hYwub"><strong>Discord</strong></a> ·
  <a href="https://blog.turso.tech/"><strong>Blog &amp; Tutorials</strong></a>
</p>

---

<h1 id="a-libsql-driver-for-doctrine" align="center">A LibSQL Driver for Doctrine DBAL</h1>

<p align="center">
    <a href="https://packagist.org/packages/darkterminal/turso-doctrine-dbal"><img src="https://img.shields.io/packagist/v/darkterminal/turso-doctrine-dbal.svg?style=flat-square" alt="Latest Version on Packagist"></a>
    <a href="https://packagist.org/packages/darkterminal/turso-doctrine-dbal"><img src="https://img.shields.io/packagist/dt/darkterminal/turso-doctrine-dbal.svg?style=flat-square" alt="Total Downloads"></a>
</p>

LibSQL is a fork of SQLite and this package is **#1 LibSQL Driver** that run natively using LibSQL Native Extension/Driver/Whatever and support Doctrine DBAL.

## Requirement

**Before using this package**, you need to install and configure **LibSQL Native Extension for PHP**. You can download from **[LibSQL Extension - Release](https://github.com/darkterminal/libsql-extension)**

1. 📦 Extract the archive
2. 🗃 Locate somewhere in your machine
3. 💽 Copy a relative path that address that extension/driver
4. 📂 Open `php.ini` search `;extension` if you using nano (ctrl+w) then searching for it
5. 📝 add in the next-line `extension=liblibsql_php.so` (in Linux) without `;` at the begining

## Package Installation

```bash
composer require darkterminal/turso-doctrine-dbal
```

## Configuration

LibSQL has 4 types of connections to interact with the database: In-Memory Connection, Local Connection, Remote Connection, and Remote Replica Connection (Embedded Replica).

### In-Memory Connection

```php
$params = [
    "url"               => ":memory:",
    'driverClass'       => \Turso\Doctrine\DBAL\Driver::class,
];
```

### Local Connection

```php
$params = [
    "url"               => "database.db",
    'driverClass'       => \Turso\Doctrine\DBAL\Driver::class,
];
```

If you want to create Remote Connection or Remote Replica (Embedded Replica) Connection, you will need an existing database to continue. If you don’t have one, **[create one](https://docs.turso.tech/quickstart)**.

Get the database URL:
```shell
turso db show --url <database-name>
```

Get the database authentication token:
```shell
turso db tokens create <database-name>
```

If you use `.env` file, assign credentials to the environment variables inside `.env`.
```env
TURSO_DATABASE_URL=
TURSO_AUTH_TOKEN=
```

### Remote Connection

```php
$params = [
    "auth_token"        => "<your-database-auth-token-from-turso>",
    "sync_url"          => "<your-database-url-from-turso>",
    'driverClass'       => \Turso\Doctrine\DBAL\Driver::class,
];
```

### Remote Replica (Embedded Replica) Connection

```php
$params = [
    "url"               => "database.db",
    "auth_token"        => "<your-database-auth-token-from-turso>",
    "sync_url"          => "<your-database-url-from-turso>",
    "sync_interval"     => 5,    // Optional, default is: 5 in seconds
    "read_your_writes"  => true, // Optional, default is: true
    "encryption_key"    => "",   // Optional, default is: empty
    'driverClass'       => \Turso\Doctrine\DBAL\Driver::class,
];
```

## Usage

```php
use Doctrine\DBAL\DriverManager;

require_once __DIR__ . '/vendor/autoload.php';

$params = [
    "url"               => ":memory:",
    'driverClass'       => \Turso\Doctrine\DBAL\Driver::class,
];

$db = DriverManager::getConnection($params);

$createTable = "CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    age INTEGER
)";
$db->executeStatement($createTable);

$insertUsers = <<<SQL
INSERT INTO users (name, age) VALUES ('Budi Dalton', 49);
INSERT INTO users (name, age) VALUES ('Sujiwo Tedjo', 50);
SQL;

$db->getNativeConnection()->executeBatch($insertUsers);

$result = $db->executeQuery("SELECT * FROM users")->fetchAllAssociative();
var_dump($result);
$db->close();
```

---

## Documentation

1. [Turso Quickstart](https://docs.turso.tech/quickstart) &mdash; Learn how create and connect your first database.
2. [SDK Quickstart](#) &mdash; Learn how to install and execute queries using the libSQL client.
3. [SDK Reference](#) &mdash; Dive deeper with the libSQL SDK reference and examples.

### What is Turso?

[Turso](https://turso.tech) is a SQLite-compatible database built on [libSQL](https://docs.turso.tech/libsql), the Open Contribution fork of SQLite. It enables scaling to hundreds of thousands of databases per organization and supports replication to any location, including your own servers, for microsecond-latency access.

Learn more about what you can do with Turso:

- [Embedded Replicas](https://docs.turso.tech/features/embedded-replicas)
- [Platform API](https://docs.turso.tech/features/platform-api)
- [Data Edge](https://docs.turso.tech/features/data-edge)
- [Branching](https://docs.turso.tech/features/branching)
- [Point-in-Time Recovery](https://docs.turso.tech/features/point-in-time-recovery)
- [Scale to Zero](https://docs.turso.tech/features/scale-to-zero)
