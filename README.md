<p align="center">
  <img alt="Turso + PHP" src="https://i.imgur.com/zRVfWL3.png" width="800">
</p>

<h1 align="center">Turso Client PHP</h1>
<p align="center">
  A community-driven PHP SDK for <a href="https://turso.tech/libsql">libSQL</a> — the SQLite fork designed for the edge, replication, and production workloads.
</p>

<p align="center">
  <a href="LICENSE"><img src="https://img.shields.io/github/license/tursodatabase/turso-client-php?color=01c3b0" alt="MIT License"></a>
  <a href="https://tur.so/discord-php"><img src="https://img.shields.io/discord/933071162680958986?color=01c3b0" alt="Discord"></a>
  <a href="#contributors"><img src="https://img.shields.io/github/contributors/tursodatabase/turso-client-php?color=01c3b0" alt="Contributors"></a>
  <a href="https://github.com/tursodatabase/turso-client-php/releases"><img src="https://img.shields.io/github/downloads/tursodatabase/turso-client-php/total.svg?color=01c3b0" alt="Downloads"></a>
  <a href="https://github.com/tursodatabase/turso-client-php/releases"><img src="https://img.shields.io/github/v/release/tursodatabase/turso-client-php?color=01c3b0" alt="Release"></a>
</p>

---

## Overview

**turso-client-php** (libSQL Extension) brings the power of [libSQL](https://turso.tech/libsql) to PHP applications. It’s more than just SQLite — with built-in support for **remote connections, replicas, sync, offline writes, and transactions**.

✨ Key features:
- 🚀 Works with **SQLite** and **libSQL**  
- 🔌 Multiple connection modes
  - local
  - in-memory
  - remote
  - remote replica
  - offline-writes also support for libsql-server (sqld)  
- 🔒 Transactions, prepared statements, and batch execution  
- 🐘 Native PHP extension built in **Rust** for speed  
- 🐳 Easy setup with `turso-php-installer`

---

## Supported Versions

| PHP Version | Build Variants |
|-------------|----------------|
| 8.1         | TS / NTS       |
| 8.2         | TS / NTS       |
| 8.3         | TS / NTS       |
| 8.4         | TS / NTS       |

**TS** = Thread Safe, **NTS** = Non Thread Safe  
✅ Supported on Linux, macOS, Windows, WSL

---

## Quick Start

```php
<?php

$libsql = new LibSQL("file:example.db");

// Create a table
$libsql->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");

// Insert a row
$libsql->execute("INSERT INTO users (name) VALUES (?)", ["Alice"]);

// Query data
$result = $libsql->query("SELECT * FROM users");
$rows = $result->fetchArray(LibSQL::LIBSQL_ASSOC);

foreach ($rows as $row) {
    echo $row["id"] . " - " . $row["name"] . PHP_EOL;
}
````

➡️ See [Quick Start Guide](docs/quick-start.md) for more details.

---

## Installation

The easiest way to install is via the Composer installer:

```bash
composer global require darkterminal/turso-php-installer
```

Add the binary to your `PATH`:

```bash
export COMPOSER_BIN_DIR=$(composer config --global home)/vendor/bin
```

Run the installer in interactive mode:

```bash
turso-php-installer install
```

Or non-interactive (example for PHP 8.3):

```bash
turso-php-installer install -n --php-version=8.3
```

📦 For alternative methods (manual build, Docker, ARM64), check [Installation Docs](docs/000-configuration.md).

---

## Documentation

* [Installation](docs/000-configuration.md)
* [Quick Start](docs/quick-start.md)
* [Connections](docs/001-local-connection.md)
* [API Reference](docs/005-LibSQL-class.md)
* [Transactions](docs/012-transaction.md)
* [Sync & Offline Writes](docs/015-sync.md)

👉 Full documentation available in the [docs/](docs/) folder.

---

## Development

Requirements:

* PHP ≥ 8.1
* Rust nightly toolchain
* Git
* Docker & Docker Compose (for containerized dev)

Build from source:

```bash
git clone git@github.com:<username>/turso-client-php.git
cd turso-client-php
rustup toolchain install nightly
rustup default nightly
cargo build
```

Or inside Docker:

```bash
make compose/up
# or for arm64
make compose-arm64/up
```

For available commands:

```bash
make help
```

---

## Community & Support

* 💬 Join us on [Discord](https://tur.so/discord-php)
* 🐛 Report issues on [GitHub Issues](https://github.com/tursodatabase/turso-client-php/issues)
* 🤝 Contributions welcome! See [Contributing Guide](CONTRIBUTING.md)

---

## Contributors

![Contributors](https://contrib.nn.ci/api?no_bot=true\&repo=tursodatabase/turso-client-php)

---

## License

This project is open-sourced under the [MIT License](LICENSE).