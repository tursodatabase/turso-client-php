# Introduction

## What is Turso Client PHP?

**Turso Client PHP** is a community-driven SDK that brings the power of [libSQL](https://turso.tech/libsql) — the open-source, production-ready fork of SQLite — into PHP applications.

It is not “just SQLite”:

* ⚡ **Designed for Production** — provides a SQLite-compatible engine enhanced with modern features.
* 🌍 **Remote & Distributed** — supports remote connections, replicas, and synchronization across environments.
* 📦 **PHP-Native** — exposed as a **PHP extension** built in Rust for performance and reliability.
* 🐘 **Familiar API** — simple, intuitive methods for executing queries, managing transactions, and working with prepared statements.

This SDK makes it possible to use Turso/libSQL as the **database layer for PHP projects**, from small local apps to distributed cloud-native systems.

---

## Why use Turso Client PHP?

Traditional SQLite is great for local development but limited for **modern distributed applications**.
With `turso-client-php`, you get:

* **Local development** using SQLite with zero setup.
* **Remote databases** running on Turso or any libSQL server.
* **Replicas** to scale reads and reduce latency.
* **Offline writes** with sync support when connectivity is restored.
* **Transactions, prepared statements, batch operations** — all natively supported.

Whether you’re building a **Laravel app**, a **CLI tool**, or a **PHP microservice**, this extension provides a bridge to modern SQLite.

---

## Supported Versions & Platforms

### PHP Versions

| PHP Version | Build Variants |
| ----------- | -------------- |
| 8.1         | TS / NTS       |
| 8.2         | TS / NTS       |
| 8.3         | TS / NTS       |
| 8.4         | TS / NTS       |

* **TS** = Thread Safe
* **NTS** = Non Thread Safe

### Operating Systems

* ✅ Linux
* ✅ macOS
* ✅ Windows / WSL

### Requirements

* PHP ≥ 8.1
* Composer (for installer)
* [Rust](https://www.rust-lang.org/tools/install) (if building from source)
* Docker (optional, for containerized development)

---

## Next Steps

* 👉 [Installation & Configuration](configuration.md)
* 👉 [Quick Start](quick-start.md)
