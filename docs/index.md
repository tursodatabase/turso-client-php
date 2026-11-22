---
layout: default
title: Turso PHP Extension
---

# Turso PHP Extension

Native database bindings for PHP, powered by [LibSQL](https://libsql.tech) and
[Turso](https://turso.tech).

The `LibSQL` PHP class gives your application a single, opinionated entry
point for opening connections, running queries, preparing statements,
executing transactions and streaming results — backed by a native Rust
extension compiled for PHP 8.1 through 8.5.

## Why

- **Native speed**: Rust extension via `ext-php-rs`, no userland PDO layer.
- **PHP-shaped API**: open flags, fetch modes (`LIBSQL_ASSOC`, `LIBSQL_NUM`, …),
  prepared statements, transactions, offline writes.
- **Production sane**: panic-free error mapping, cleanup on drop/finalize,
  deterministic ordering, observability hooks.

## Start

- [Quick Start](/quick-start.md) — install and run your first query.
- [Configuration](/configuration.md) — DSN, flags, connection modes.
- [Remote / Local / Memory / Embedded Replica](/remote-connection.md) — connection flavors.
- [Class references](/LibSQL-class.md), [Result](/LibSQLResult-class.md),
  [Statement](/LibSQLStatement-class.md), [Transaction](/LibSQLTransaction-class.md).
- [Error Semantics](/error-semantics.md) — how errors surface to PHP.
- [Offline Writes](/offline-writes-sqld-connection.md) — SQLD offline writes.
- [Production Deployment](/production-deployment.md).

## Versions

- PHP: 8.1 – 8.5 (`nts` / `ts`).
- Extension releases: tagged as `turso-php-extension-vX.Y.Z`.

---

The rest of this site is the documentation and user manual for the extension.
