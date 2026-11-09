# End-to-End Compatibility Suite

`e2e.php` is a **dependency-free, deterministic, offline** end-to-end suite for
the compiled extension. It audits two things with the same scenarios:

1. **Behavioral contract** — locks the engine behaviors the documentation
   describes: fetch shapes, LAZY streaming, transaction semantics, exact error
   messages, accounting, and the offline-write journal lifecycle.
2. **PHP-version compatibility** — the suite must pass unchanged on every PHP
   version documented as supported in the [README](../../README.md)
   ("Supported Versions": 8.1–8.5). Because `ext-php-rs` generates
   PHP-version-specific FFI bindings at build time, each version also needs its
   own *build*; `run-matrix.sh` automates that (and `.github/workflows/
   e2e-matrix.yml` runs it on CI).

Nothing in the suite is network-dependent: the only "remote" it contacts is the
guaranteed-dead port `127.0.0.1:9`, used to simulate an offline peer for the
journaled offline-write flavor. All temp databases are created under
`sys_get_temp_dir()` and unlinked by a shutdown handler.

## Run a single version

```bash
cargo build --quiet   # against the PHP your php-config points to
php -n -d extension_dir="$(pwd)/target/debug" \
    -d extension=liblibsql_php.so \
    tests/e2e/e2e.php
```

Exit code `0` plus the `E2E-REPORT: {...}` JSON line means pass. The report
carries the PHP version, extension version string, per-status counters, and
duration.

## Run the multi-version matrix

```bash
tests/e2e/run-matrix.sh              # default: 8.1 8.2 8.3 8.4 8.5
tests/e2e/run-matrix.sh 8.2 8.4      # any subset
```

For each version the runner

* verifies `php<v>` + `php-config<v>` exist (skips with an explicit row
  otherwise),
* builds the extension into an **isolated** target dir
  (`CARGO_TARGET_DIR=target/php<v>`, `PHP=/usr/bin/php<v>`,
  `PHP_CONFIG=/usr/bin/php-config<v>`) — see
  [CONTRIBUTING.md](../../CONTRIBUTING.md#picking-the-right-php-config) for why
  sharing one build across PHP versions is unsafe,
* runs `e2e.php` under that exact runtime,
* optionally runs the Pest suite (`vendor/bin/pest`) when the installed
  composer stack supports that runtime — skipped with a note otherwise. It is
  *never* run against a PHP where the system ini already loads `libsql_php`
  (e.g. machine-wide installs): that would unit-test a binary other than the
  freshly built one, which is exactly the false proof the matrix exists to
  avoid. The dependency-free E2E suite remains the proof for those versions.
* prints one `PASS/FAIL/SKIP` row per version and `MATRIX-REPORT:` lines
  consumed by CI.

## Scenario coverage (12 scenarios)

| # | Scenario | Locked behavior |
| - | -------- | --------------- |
| 1 | local file connection lifecycle | persistence across `close()`/reopen; UPDATE visible after reopen |
| 2 | changes()/totalChanges() accounting | DDL does not count; `changes()` = last statement; `totalChanges()` accumulates rows |
| 3 | typed bindings | int/float/bool/null/string round-trip types preserved; bool bind → INTEGER `1`; int-list → BLOB → returned as int array `[1,2,255,0]`; positional path |
| 4 | prepared reuse loop | re-binding per `execute()` inside a loop; `reset()` + re-bind |
| 5 | fetch modes | `LIBSQL_ASSOC` / `LIBSQL_NUM` / `LIBSQL_BOTH` (named key interleaved with its numeric twin) / `LIBSQL_ALL` structure keys; column order follows the query; default mode BOTH; `fetchSingle()` ⇒ BOTH + `[]` on zero rows; a result re-executes per fetch (live view) |
| 6 | LAZY streaming | `LibSqlIterator` (canonical spelling); 3 rows ⇒ exactly 3 rounds; `key()` 0-based; after end `current() === null`/`valid() === false`; `rewind()` does not restart a row-set iterator; zero rows ⇒ one null round |
| 7 | LAZY fetchSingle | walks the single row and its iterator *does* restart on `rewind()` (array-backed); public `LibSqlIterator` over a plain array |
| 8 | transactions | `isAutocommit()` false inside; `commit()` restore; second `commit()`/`rollback()` throws `Transaction not found`; rollback discards writes; default `transaction()` commits |
| 9 | error semantics | exact engine messages (see
[docs/error-semantics.md](../../docs/error-semantics.md)): `Failed to parse DSN`, prepare/execute syntax errors, DML-only rule (`Execute returned rows`), `Statement not found`, `Connection ID not found.`, `Connection not found`, deferred query-time failures, `Column index 5 out of bounds`, `columnType()` OOB ⇒ `"Null"`, named-argument rule throws `ArgumentError` |
| 10 | offline-write journal | construct works offline at a dead port; `prepare()`/`transaction()` gated with the exact guard message; sync fails with `Sync failed: Cannot sync: no internet connection`; pending ops preserved; `Offline connection not found` after close; **pending ops + data survive close/reopen** |
| 11 | metadata | `parameterCount()`/`parameterName()` (`:name` without prefix; index 0 null); `columns()` keys; zero-row results still carry declared column metadata; `columnType()` per-row types |
| 12 | remote laziness | remote construction does no network I/O; failure surfaces only at first use with the exact Hrana message |

Exact message asserts are version-independent by construction: the strings are
produced by the *Rust* engine (`libsql` stack + this extension), not by the
Zend API layer, so they are byte-identical across the supported PHP versions.

## Verified results

Local verification on Linux x86-64 NTS builds (extension 1.6.2, libSQL core
3.45.1), via `tests/e2e/run-matrix.sh` (all five versions green in one run):

| PHP | E2E suite | Pest smoke | Notes |
| --- | --------- | ---------- | ----- |
| 8.1 | ✅ 12/12 (0.5s) | skipped | Pest 4 requires PHP ≥ 8.2; ondrej `php8.1-dev` headers (API 20210902) |
| 8.2 | ✅ 12/12 (0.5s) | skipped | the vendored PHPUnit telemetry needs the PHP ≥ 8.3 `gc_status()` fields; ondrej build (API 20220829) |
| 8.3 | ✅ 12/12 (0.51s) | ✅ 33 passed / 127 assertions | ondrej build (API 20230831) |
| 8.4 | ✅ 12/12 (0.5s) | ✅ 33 passed / 127 assertions | ondrej build (API 20240924) |
| 8.5 | ✅ 12/12 (0.52s) | skipped | the system ini loads `libsql_php` — the smoke would prove the wrong binary (see above); ondrej build (API 20250925) |

CI runs the same proofs per PR/push via
[`e2e-matrix.yml`](../../.github/workflows/e2e-matrix.yml) — one job per PHP
version, reports appended to the job summary.

## Design notes

* **No vendor/ dependency.** The suite runs with `-n` (no php.ini), so nothing
  except the extension under test influences behavior. The Pest suite (which
  needs composer) covers unit/feature granularity separately.
* **Every assertion is empirically verified.** The quoted messages and shapes
  reflect *observed* engine behavior, not documentation intentions. If a new
  PHP version changes any behavior, the suite fails loudly instead of drifting
  silently.
* **Deferred vs direct failures are respected.** `query()` defers statement
  errors to the fetch call by design; the suite asserts on the layer where the
  failure actually surfaces.
* **Horizon of the offline flavor.** Scenario 10 covers only the journaled
  offline-write flavor; the Turso Cloud β flavor requires an online endpoint
  and is covered by the unit tests instead.
