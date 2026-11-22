# 📚 Turso Client PHP Documentation

## Introduction

* [What is Turso Client PHP?](user-manual/index.md)
* [Supported Versions & Platforms](user-manual/index.md#supported-versions)

---

## Getting Started

* [Installation & Configuration](user-manual/configuration.md)
* [Quick Start](user-manual/getting-started.md)

---

## Connections

* [Local Connection](user-manual/local-connection.md)
* [In-Memory Connection](user-manual/memory-connection.md)
* [Remote Connection](user-manual/remote-connection.md)
* [Embedded Replica Connection](user-manual/embedded-replica-connection.md)
* [Offline Writes (Turso Cloud)](user-manual/offline-writes-turso-connection.md)
* [Offline Writes (sqld/libsql-server)](user-manual/offline-writes-sqld-connection.md)
* [Offline Writes: Flavor Comparison](user-manual/offline-writes-flavors.md)

---

## Production

* [Production Deployment Guide](user-manual/production-deployment.md) — flavors, the outage playbook, durability, concurrency discipline, and upgrading with the proof as the gate
* [Production Usage Proof (docker compose)](../docker/production-proof/README.md) — the runnable claim → assert map behind the guide

---

## Core API

* [LibSQL Class](user-manual/reference/libsql-class.md)
* [LibSQLStatement Class](user-manual/reference/statement-class.md)
* [LibSQLTransaction Class](user-manual/reference/transaction-class.md)
* [LibSQLResult Class](user-manual/reference/result-class.md)
* [Constants and Fetch Modes](user-manual/reference/constants.md)

---

## Development Guide

* [Building from Source](../README.md#development)
* [Contributing Guidelines](../CONTRIBUTING.md)
* Running Tests (Pest — see the Contributing Guidelines)
* Docker-based Development (`compose.yaml`)

---

## Reference & Appendices

* [Error Semantics](user-manual/error-semantics.md) — what throws, when, and the exact messages
* [API Reference (stubs)](../libsql_php_extension.stubs.php)
* [License](../LICENSE)
