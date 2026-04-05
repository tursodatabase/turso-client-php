# Turso Client PHP — Reference Documentation

> A community-driven PHP SDK for libSQL — the SQLite fork designed for the edge, replication, and production workloads. Built as a native PHP extension in Rust.

---

## 📚 Document Index

| # | Document | Description |
|---|----------|-------------|
| 01 | [Core Architecture Deep Dive](01-core-architecture.md) | Registry pattern, async runtime bridge, type conversion pipeline, mode detection algorithm, error handling strategy |
| 02 | [Connection Modes](02-connection-modes.md) | Complete reference for all 5 connection modes: local, remote, remote_replica, offline_write (Turso), sqld_offline_write (self-hosted) |
| 03 | [PHP API Reference](03-php-api-reference.md) | Complete class/method documentation for LibSQL, LibSQLTransaction, LibSQLStatement, LibSQLResult, LibSQLIterator |
| 04 | [Offline Writes Deep Dive](04-offline-writes-deep-dive.md) | Architecture, initialization flow, write/sync flows, parameter serialization, connectivity checking, edge cases |
| 05 | [Testing Strategy & CI/CD](05-testing-and-ci.md) | Test framework, test categories, CI/CD pipeline, cross-compilation matrix, Docker development environment |
| 06 | [Patterns & Playbooks](06-patterns-and-playbooks.md) | Design patterns used, anti-patterns found, practical step-by-step playbooks for common tasks |

---

## 🏗️ Quick Architecture Overview

```
PHP Application
    │
    ▼
┌─────────────────────────────────────────┐
│         Native PHP Extension            │
│         (Rust via ext-php-rs)           │
│                                         │
│  LibSQL    LibSQLTransaction            │
│  LibSQLStatement  LibSQLResult          │
│  LibSQLIterator                        │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │      Global Registries            │  │
│  │  CONNECTION_REGISTRY              │  │
│  │  OFFLINE_CONNECTION_REGISTRY      │  │
│  │  TRANSACTION_REGISTRY             │  │
│  │  STATEMENT_REGISTRY               │  │
│  └───────────────────────────────────┘  │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │        Providers                  │  │
│  │  local  │  remote                │  │
│  │  remote_replica                   │  │
│  │  offline_write  │  sqld_offline   │  │
│  └───────────────────────────────────┘  │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │        Hooks                      │  │
│  │  use_exec │ use_query │ changes   │  │
│  │  use_exec_batch │ close │ version │  │
│  └───────────────────────────────────┘  │
│                                         │
│  ┌───────────────────────────────────┐  │
│  │        Utils                      │  │
│  │  runtime (Tokio)                  │  │
│  │  query_params │ config_value      │  │
│  │  result_set │ log_error           │  │
│  └───────────────────────────────────┘  │
└─────────────────┬───────────────────────┘
                  │
                  ▼
          ┌───────────────┐
          │  libSQL crate │
          │  (Rust)       │
          └───────┬───────┘
                  │
                  ▼
          ┌───────────────┐
          │  SQLite Engine│
          │  + libSQL     │
          │  extensions   │
          └───────────────┘
```

---

## 🔑 Key Concepts

### 1. Registry Pattern
All heavy Rust objects (connections, transactions, statements) live in global `Mutex<HashMap>` registries. PHP objects are lightweight — they only hold UUID string references.

### 2. Async Bridge
A single global Tokio runtime (`OnceCell<Runtime>`) bridges PHP's synchronous world with libSQL's async internals via `runtime().block_on(async { ... })`.

### 3. Mode Detection
Connection mode is determined automatically during construction based on DSN/config format:
- `file:` or `.db` → **local**
- `libsql://` + auth token → **remote**
- Local file + auth token + sync URL → **remote_replica**
- Same + `offline_writes: true` → **offline_write** or **sqld_offline_write**

### 4. Offline Writes
The most complex feature. Dual local+remote connections with a persistent operation queue (`libsql_pending_ops` table). Writes always succeed locally; sync happens when online.

### 5. Type Pipeline
PHP arrays ↔ Rust structs via `FromZval`/`IntoZval` traits. `QueryParameters` converts PHP arrays to `libsql::params::Params`. `ResultSet` converts query results back to PHP arrays.

---

## 🚀 Getting Started

### Prerequisites
- PHP ≥ 8.1
- Rust nightly toolchain
- Git
- Docker (optional, for containerized dev)

### Quick Build
```bash
git clone git@github.com:tursodatabase/turso-client-php.git
cd turso-client-php
rustup toolchain install nightly
rustup default nightly
cargo build
```

### Quick Test
```bash
composer install
composer test
```

### Docker Development
```bash
make compose/up        # x86_64
make compose-arm64/up  # ARM64
```

---

## 📁 Project Structure

```
turso-client-php/
├── src/                          # Rust source code
│   ├── lib.rs                    # Main entry point, LibSQL PHP class
│   ├── providers/                # Connection mode implementations
│   │   ├── mod.rs
│   │   ├── local.rs              # Local file/in-memory
│   │   ├── remote.rs             # Remote HTTP connection
│   │   ├── remote_replica.rs     # Replica with auto-sync
│   │   ├── offline_write.rs      # Turso Cloud offline writes
│   │   └── sqld_offline_write.rs # Self-hosted offline writes
│   ├── hooks/                    # Database operation handlers
│   │   ├── mod.rs
│   │   ├── use_exec.rs           # Execute statement
│   │   ├── use_exec_batch.rs     # Execute batch
│   │   ├── use_query.rs          # Query and return results
│   │   ├── changes.rs            # Rows affected
│   │   ├── is_autocommit.rs      # Autocommit status
│   │   ├── close.rs              # Close connection
│   │   ├── version.rs            # Get version
│   │   └── load_extensions.rs    # SQLite extension loading
│   ├── utils/                    # Utility modules
│   │   ├── mod.rs
│   │   ├── runtime.rs            # Tokio runtime, DSN parsing, mode detection
│   │   ├── query_params.rs       # PHP → libsql parameter conversion
│   │   ├── config_value.rs       # PHP config parsing
│   │   ├── result_set.rs         # Query result → PHP array conversion
│   │   └── log_error.rs          # Error logging to temp files
│   ├── transaction.rs            # LibSQLTransaction PHP class
│   ├── statement.rs              # LibSQLStatement PHP class
│   ├── result.rs                 # LibSQLResult PHP class
│   └── generator.rs              # LibSQLIterator PHP class
├── tests/                        # PHP tests
│   ├── Pest.php                  # Test configuration
│   ├── TestCase.php              # Base test class
│   ├── ArchTest.php              # Architecture rules
│   ├── Unit/                     # Connection unit tests
│   └── Feature/                  # Integration tests
├── examples/                     # Usage examples
│   ├── local.php
│   ├── memory.php
│   ├── remote.php
│   ├── remote-replica.php
│   ├── offline-writes.php
│   └── sqld-offline/
├── docs/                         # User documentation
├── scripts/                      # Release automation scripts
├── .github/workflows/            # CI/CD pipelines
├── Cargo.toml                    # Rust dependencies
├── composer.json                 # PHP dev dependencies
├── Makefile                      # Docker commands
├── Dockerfile                    # x86_64 dev container
├── Dockerfile.arm64              # ARM64 dev container
├── compose.yaml                  # Docker Compose config
├── libsql_php_extension.stubs.php # PHP IDE stubs
└── rust-toolchain.toml           # Rust nightly pin
```

---

## 🔧 Technology Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Extension FFI** | ext-php-rs | 0.15.3 |
| **Database Engine** | libsql | 0.9.19 |
| **Async Runtime** | tokio | 1.47.1 |
| **HTTP Client** | reqwest | 0.12.22 |
| **Serialization** | serde + serde_json | 1.0.x |
| **PHP Versions** | PHP | 8.1 – 8.5 |
| **Test Framework** | Pest PHP | 4.3 |
| **Build** | Cargo | — |
| **CI/CD** | GitHub Actions | — |
| **Container** | Docker Compose | — |

---

## 📖 Related Documents

- [README.md](../README.md) — Project overview and quick start
- [REPOSITORY_ANALYSIS.md](../REPOSITORY_ANALYSIS.md) — High-level repository analysis and AI skill definition
- [docs/](../docs/) — User-facing documentation

---

## 🏷️ Version

This reference was created for **libSQL PHP Extension v1.6.2**.
