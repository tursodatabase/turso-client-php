# turso-client-php

## Project Overview

**turso-client-php** is a community-driven PHP SDK for [libSQL](https://turso.tech/libsql) — a fork of SQLite designed for edge computing, replication, and production workloads. The project is a **native PHP extension built in Rust** using the `ext-php-rs` framework, providing high-performance database access to PHP applications.

### Key Features

- **Multiple Connection Modes**: local, in-memory, remote, remote replica, and offline writes (for libsql-server/sqld)
- **Transactions, prepared statements, and batch execution**
- **Sync & Offline Writes** support for distributed systems
- **Cross-platform**: Linux, macOS, Windows (PHP 8.1–8.5, both TS and NTS builds)
- **Built in Rust** for speed and safety

### Architecture

The extension is structured as follows:

```
src/
├── lib.rs           # Main extension entry point, defines LibSQL PHP class
├── statement.rs     # LibSQLStatement class implementation
├── transaction.rs   # LibSQLTransaction class implementation
├── result.rs        # LibSQLResult class implementation
├── generator.rs     # Code generation utilities
├── hooks/           # Database operation hooks
├── providers/       # Connection providers (local, remote, remote_replica, offline_write)
└── utils/           # Utility functions (DSN parsing, runtime, config parsing)
```

The extension registers several PHP classes:
- `LibSQL` — Main database connection class
- `LibSQLStatement` — Prepared statement handling
- `LibSQLTransaction` — Transaction management
- `LibSQLResult` — Query result handling
- `LibSQLIterator` — Iterator for result sets

Connection states are managed via static registries (Mutex-protected HashMaps) for connections, transactions, and statements.

## Building and Running

### Prerequisites

- **PHP ≥ 8.1** (8.1–8.5 supported)
- **Rust nightly toolchain**
- **Git**
- **Docker & Docker Compose** (optional, for containerized development)

### Build from Source

```bash
# Clone the repository
git clone git@github.com:<username>/turso-client-php.git
cd turso-client-php

# Ensure nightly Rust toolchain
rustup toolchain install nightly
rustup default nightly

# Build the extension
cargo build
```

### Docker Development

```bash
# Start development container (x86_64)
make compose/up

# Start development container (ARM64)
make compose-arm64/up

# View logs
make compose/logs

# Restart
make compose/restart

# Stop
make compose/down
```

The Docker setup runs a PHP 8.5 development server on port `6969`.

### Release Builds

Release builds are automated via GitHub Actions (`.github/workflows/cross-compile.yml`). When a release is created, the workflow cross-compiles the extension for:
- Linux (x86_64)
- macOS (x86_64 and aarch64) on macOS 14 and 15
- Windows (x86_64)

Across all supported PHP versions (8.1–8.5) and both TS/NTS variants.

## Testing

The project uses [Pest PHP](https://pestphp.com/) (a PHP testing framework built on PHPUnit) for testing.

```bash
# Run all tests
composer test

# Run feature tests only
composer test:feature
```

Tests are organized in:
- `tests/Unit/` — Unit tests
- `tests/Feature/` — Feature tests (CRUD, transactions, batch operations, schema operations, prepared statements)

The test base class (`tests/TestCase.php`) sets up an in-memory database connection for each test.

## Key Configuration Files

| File | Purpose |
|------|---------|
| `Cargo.toml` | Rust dependencies and build configuration |
| `composer.json` | PHP dev dependencies (Pest) and test scripts |
| `rust-toolchain.toml` | Pins Rust to `nightly` channel |
| `phpunit.xml` | PHPUnit/Pest configuration |
| `compose.yaml` | Docker Compose setup for development |
| `Dockerfile` / `Dockerfile.arm64` | Docker images for x86_64 and ARM64 builds |
| `libsql_php_extension.stubs.php` | PHP stubs for IDE autocompletion |

## Development Conventions

- **Rust**: Uses `ext-php-rs` crate for PHP extension development. Requires `nightly` Rust due to `abi_vectorcall` feature.
- **PHP Tests**: Uses Pest PHP with a base `TestCase` class that provides an in-memory database.
- **Error Handling**: Errors are logged to tmp files via `log_error_to_tmp` utility and thrown as `PhpException`.
- **Connection Management**: Connections, transactions, and statements are tracked in static Mutex-protected HashMaps, identified by UUIDs.

## Documentation

### Primary Reference Documentation

For complete context and project understanding, **always reference the `references/` directory first**. These documents provide the authoritative deep-dive into the extension's architecture, patterns, and implementation details:

| Document | Description |
|----------|-------------|
| `references/README.md` | Index and quick overview of all reference docs |
| `references/REPOSITORY_ANALYSIS.md` | Complete repository analysis and system architecture |
| `references/TECHNICAL_REVIEW.md` | Comprehensive technical review with recommendations |
| `references/01-core-architecture.md` | Registry pattern, async runtime bridge, type conversion, mode detection, error handling |
| `references/02-connection-modes.md` | Complete reference for all 5 connection modes |
| `references/03-php-api-reference.md` | Full PHP API documentation (classes, methods, usage) |
| `references/04-offline-writes-deep-dive.md` | Offline writes architecture, initialization, sync flows, edge cases |
| `references/05-testing-and-ci.md` | Testing strategy, CI/CD pipeline, cross-compilation matrix |
| `references/06-patterns-and-playbooks.md` | Design patterns, anti-patterns, practical step-by-step playbooks |

### User-Facing Documentation

Additional documentation is available in the `docs/` directory:
- `docs/configuration.md` — Installation and setup
- `docs/quick-start.md` — Getting started guide
- `docs/local-connection.md` — Local database connections
- `docs/memory-connection.md` — In-memory connections
- `docs/remote-connection.md` — Remote (Turso Cloud) connections
- `docs/embedded-replica-connection.md` — Embedded replica setup
- `docs/offline-writes-sqld-connection.md` — Offline writes for sqld
- `docs/offline-writes-turso-connection.md` — Offline writes for Turso Cloud
- `docs/LibSQL-class.md` — API reference

## Development Session Workflow

**Before starting any work, always check the progress tracker:**

```bash
# Show current progress and pending tasks
scripts/check-progress.sh
```

The enhancement roadmap is tracked in `PROGRESS.md`. Rules:
1. Read `PROGRESS.md` to understand current status and what's next
2. Mark the task you're starting as `in_progress`
3. Mark completed tasks as `completed` with a brief note
4. Commit changes after each atomic unit of work
5. Update `PROGRESS.md` to reflect the completed work

## Quick Usage Example

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
```

## License

MIT License — see `LICENSE` for details.
