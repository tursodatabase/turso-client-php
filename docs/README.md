# 📚 Turso Client PHP Documentation

## Introduction

* [What is Turso Client PHP?](introduction.md)
* [Supported Versions & Platforms](introduction.md#supported-versions)

---

## Getting Started

* [Installation & Configuration](configuration.md)
* [Quick Start](quick-start.md)

---

## Connections

* [Local Connection](local-connection.md)
* [In-Memory Connection](memory-connection.md)
* [Remote Connection](remote-connection.md)
* [Embedded Replica Connection](embedded-replica-connection.md)
* [Offline Writes (Turso Cloud)](offline-writes-turso-connection.md)
* [Offline Writes (sqld/libsql-server)](offline-writes-sqld-connection.md)
* [Offline Writes: Flavor Comparison](offline-writes-flavors.md)

---

## Production

* [Production Deployment Guide](production-deployment.md) — flavors, the outage playbook, durability, concurrency discipline, and upgrading with the proof as the gate
* [Production Usage Proof (docker compose)](../docker/production-proof/README.md) — the runnable claim → assert map behind the guide

---

## Core API

* [LibSQL Class](LibSQL-class.md)
* [LibSQLStatement Class](LibSQLStatement-class.md)
* [LibSQLTransaction Class](LibSQLTransaction-class.md)
* [LibSQLResult Class](LibSQLResult-class.md)
* [Constants and Fetch Modes](constants.md)

---

## Development Guide

* [Building from Source](../README.md#development)
* [Contributing Guidelines](../CONTRIBUTING.md)
* Running Tests (Pest — see the Contributing Guidelines)
* Docker-based Development (`compose.yaml`)

---

## Reference & Appendices

* [Error Semantics](error-semantics.md) — what throws, when, and the exact messages
* [API Reference (stubs)](../libsql_php_extension.stubs.php)
* [License](../LICENSE)