# Contributing to Turso Client PHP

Thanks for helping improve this edition! 🎉 This project is a community-driven
native PHP extension for libSQL, written in **Rust** (via `ext-php-rs`) and
exposed to PHP as the `LibSQL` class family. This guide covers everything
you need to develop, test, and ship changes.

## Ground Rules

1. **Never panic on foreseeable input.** The engine should never abort the
   PHP request because a URL was malformed, a token was missing, or SQL was
   invalid. Everything user-facing maps to a thrown `PhpException`.
2. **Clean up after yourself, including at GC time.** Native resources
   (connections, prepared statements, transactions) are tracked in registry
   maps and must be dropped when the PHP objects are destroyed — see the
   `impl Drop` blocks in `src/lib.rs`, `src/statement.rs`, and
   `src/transaction.rs` for the pattern to follow.
3. **Match the docs and stubs.** Public behavior is documented in `docs/`
   and machine-readable in `libsql_php_extension.stubs.php`. If you change
   an API surface, update both in the same PR.

## Requirements

* PHP ≥ 8.1 with development headers (`phpize`/`php-config`) — 8.1–8.5
* Rust **nightly** toolchain (the extension binds `ext-php-rs`)
* Git
* Docker + Docker Compose (optional, containerized dev)
* Composer (for the test harness)

## Build From Source

```bash
git clone git@github.com:<you>/turso-client-php.git
cd turso-client-php

rustup toolchain install nightly
rustup default nightly

cargo build
```

### Picking the right `php-config`

`ext-php-rs` generates bindings against the PHP headers of the toolchain
that `php-config` points to. If you have multiple PHP versions installed,
the default `php-config` may disagree with the `php` you actually run, and
you will get extension-load errors like *"module compiled with module
API=..."* at the `php` CLI.

Point the build at the `php-config` matching your runtime PHP explicitly:

```bash
PHP_CONFIG=/usr/bin/php-config8.4 cargo build
```

The shared library is emitted as `target/debug/liblibsql_php.so`.

### Loading the freshly built extension

Run a php process with just your fresh build loaded (no `php.ini` at all):

```bash
php -n -d extension_dir="$(pwd)/target/debug" -d extension=liblibsql_php.so -r '
    $db = new LibSQL("file::memory:");
    echo $db->version() . PHP_EOL;
    $db->close();
'
```

## Running the Tests

The PHP suite is [Pest](https://pestphp.com), bootstrapped through
`vendor/autoload.php`:

```bash
composer install

NO_COLOR=1 php -d extension="$(pwd)/target/debug/liblibsql_php.so" \
    vendor/bin/pest
```

Feature tests (the ones committed alongside new API behavior) can be
filtered:

```bash
composer test:feature
```

Notes about the suite:

* `tests/Pest.php` deletes `database.db` and `memory` from the working
  directory after **every** test. Don't depend on files from previous runs.
* **Don't write `not->toThrow(Exception::class)` assertions.** Failures
  thrown by the engine on bad arguments are typically `Error` subclasses
  (e.g. `ArgumentError`), which are not `Exception` instances — so such an
  assertion passes vacuously and the behavior is never really exercised.
  Assert on `Throwable::class` instead, or better, assert on the actual
  values/side effects you expect.

### End-to-end compatibility suite

`tests/e2e/e2e.php` is a dependency-free, offline, end-to-end suite that runs
with the freshly built `.so` under a bare `php -n` process — see
[tests/e2e/README.md](tests/e2e/README.md) for the scenario table. It proves
user-observable engine contracts (fetch shapes, LAZY streaming, transactions,
exact error messages, offline journal) on the *binary you build*, which is
exactly the artifact that differs between PHP versions.

For multi-version proofs use the matrix runner (each PHP version gets its own
isolated `cargo` target directory, because `ext-php-rs` bindings are baked per
version):

```bash
tests/e2e/run-matrix.sh          # 8.1–8.5 (default), or a subset: ./run-matrix.sh 8.2 8.4
```

When your change affects any behavior covered there, run the suite (or the
full matrix) and keep the "Verified results" table in the e2e README honest.

## Writing PHP Examples That Actually Run

Small PHP-engine detail, learned from the docs and tests: extension
functions with unknown default values cannot have named-argument slots
skipped. If a constructor takes

```
__construct($config, $sqld_offline_mode, $flags, $encryption_key,
            $offline_writes)
```

then

```php
new LibSQL(config: $config, flags: LibSQL::OPEN_READWRITE, ...) // 💥 ArgumentError
```

fails with *"Argument #2 ($sqld_offline_mode) must be passed explicitly,
because the default value is not known"* — skipping
`$sqld_offline_mode` while passing later named arguments is not allowed.
All-positional calls, or explicitly passing each not-yet-provided named
argument, work. Any example snippet in `docs/` or test fixtures must pass
every named parameter before the last one it supplies.

## Smoke Testing

The Pest suite is broad but shallow. Before submitting anything touching
runtime behavior, script the specific path you changed end-to-end — config
parsing, a write, a sync, an offline round trip, GC cycles — and run it
against the freshly built `.so`:

```bash
# example: cycle connections and verify no resource growth
for i in $(seq 1 30); do
  php -n -d extension_dir="$(pwd)/target/debug" -d extension=liblibsql_php.so \
      -r '(new LibSQL("file::memory:"))->close();'
done
```

Repeatability matters: if your change affects anything order-sensitive
(result row ordering, parameter ordering, journal replay), run the same
smoke script many times before trusting a single pass.

### Production-usage proof (docker compose)

`docker/production-proof/proof.sh` brings up the production topology —
nginx → php-fpm 8.5 (release-built extension installed via `conf.d` ini) →
official libSQL server — and runs a workload through the public HTTP tier
with asserts on the hard production claims: remote CRUD visibility from a
second client, the offline-write journal round trip across a staged
network outage (journal-only writes → reconnect → the rows visible
server-side, verified by a second client), app-restart and
server-restart durability, and an in-process connection-churn leak guard.
It ends with a `PROOF: {...}` report and a
non-zero exit when anything fails; see
[docker/production-proof/README.md](docker/production-proof/README.md)
for the claim → assert map. Run it before merging changes to sync/offline
paths or anything that touches connection lifecycles.


## Style & Quality Checklist

* `cargo fmt` (CI-enforced formatting)
* zero compiler warnings (`cargo check` is clean)
* Prefer `ok_or_else`/`map_err` chains over `unwrap`/`expect` — any
  `unwrap` you add must be provably unreachable
* Update `docs/`, `libsql_php_extension.stubs.php`, and the class doc when
  behavior users can observe changes
* Add Pest coverage for new behavior, with real value assertions
* Verify both connection flavors when touching offline-write code: the
  journaled flavor (`sqld_offline_mode: true` + `offline_writes: true`)
  and the Turso Cloud β flavor (`offline_writes: true` +
  `sqld_offline_mode: false`) behave differently on purpose

## Docker Development

```bash
make compose/up        # standard
make compose-arm64/up  # Apple Silicon
make help              # all targets
```

## Submitting a Pull Request

1. Fork → feature branch (`fix/...`, `feat/...`, `docs/...`)
2. Commit with a descriptive message; one logical concern per commit
3. Make sure the Pest suite passes with **the freshly built extension**,
   and mention in the PR body how you smoke-tested anything the suite does
   not cover
4. PR descriptions: include a short "what to verify" section — reviewers
   who can reproduce your steps can review twice as fast.
