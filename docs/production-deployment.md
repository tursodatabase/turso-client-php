# Production Deployment Guide

Everything in this page is backed by the runnable proof harness in
[`docker/production-proof/`](../docker/production-proof/README.md) — a docker
compose topology (nginx → php-fpm → live libSQL server) whose runner asserts
the production claims and emits a machine-readable `PROOF: {...}` report
(**24 asserts / 0 fail** in the reference run). If you skip everything else,
run that harness against your target versions before going live; it is the
executable version of this guide.

```
host → nginx :80 → php-fpm 8.5 (+ .so + conf.d ini) → libSQL server :8080
                        │                                    │
                 volume: app DB +                    volume: server DB
                 offline journal
```

---

## 1. Supported runtime surface

| Dimension | Proven | Honest boundary |
| --------- | ------ | --------------- |
| PHP versions | **8.1, 8.2, 8.3, 8.4, 8.5** — per-version E2E suite 12/12 on each (one binary per version: `ext-php-rs` bakes bindings at build time) | One `.so` per PHP API; do not reuse across versions |
| PHP SAPI | **PHP-FPM (NTS)** — the reference proof ran 8.5.12 as `fpm-fcgi` behind nginx | ZTS (Apache mod_php equivalent), workers/coroutine runtimes: untested, treat as unsupported |
| OS | Linux x86-64 (the proof) | macOS/Windows per platform docs, not proof-covered |
| Server | Official `ghcr.io/tursodatabase/libsql-server` (proof: `0.24.33`) and native Hrana remote endpoints | Turso Cloud β flavor follows different semantics (see the flavor matrix) |
| Extension | `1.6.2` engine `3.45.1-3045001` at proof time | Check what you run: `php -r 'var_dump(LibSQL::version());'` prints the core and extension versions |

## 2. Install it the packaged way

The proof installs the extension exactly like a distribution would, and so
should production:

1. Ship `liblibsql_php.so` (release build, matching your PHP API — e.g.
   `/usr/lib/php/20250925/` for PHP 8.5).
2. Enable it with an ini drop-in in `conf.d` **for both the SAPI you serve
   (fpm) and the CLI you operate with**:

   ```ini
   ; /etc/php/8.5/fpm/conf.d/90-libsql.ini
   extension=liblibsql_php.so
   ```

3. Never run production with `-d extension=` flags — that mode is for dev
   smoke only.

Verify from the SAPI you actually serve (the proof asserts it through fpm):

```php
LibSQL::version(); // "LibSQL Core Version : …\nLibSQL PHP Extension Version: …"
```

## 3. Pick your flavor (what you deploy decides what you configure)

| Flavor | When | Setup + notes | Proof tier |
| ------ | ---- | ------------- | ---------- |
| **Remote (Hrana)** | App talks to a sqld/libSQL server over HTTP; state lives server-side | `new LibSQL('libsql:dbname=https://…;authToken=<jwt>')` — connections open lazily; the first statement is what fails if the server is unreachable. Works per-request (open → work → `close()`), the fpm-idiomatic pattern | `main` |
| **Local file** | State on the machine (queues, caches, default state) | `new LibSQL('file:/var/app/data/proof.db')` — durable on a volume; survives process restarts | `main` + `persist` |
| **Journaled offline writer** | Writes must survive server outages and sync later | `sqld_offline_mode: true`, `offline_writes: true`, `syncUrl`, and a **non-empty `authToken`** | `offline-write` + `offline-sync` |

The flavor comparison page
([offline-writes-flavors.md](offline-writes-flavors.md)) documents the gates
byte-exact — including that an **empty `authToken` silently does not activate
the journal** (you then see `Pending operations only available in
offline_write mode` from `getPendingOperationsCount()`). Treat a non-empty
token as part of the flavor contract.

Inside `offline_writes` mode, `prepare()` and `transaction()` throw on
purpose — they execute outside the write journal and their writes would
never replay on sync. The supported write paths are `execute()` /
`query()` with bound (positional) parameters as the second argument.

## 4. Topology that is proven end-to-end

The reference deployment is deliberately boring:

* **nginx** as the public entry, proxying FastCGI to **php-fpm :9000**
* **php-fpm** workers whose requests open, use, and close connections in the
  flavor they need — no global/shared LibSQL state
* **sqld** in its own container, database on a named volume
* split networks (`web-net` public path vs `server-net` app→server path) so a
  *server outage does not take the web tier down with it*
* data ownership bootstrap on startup: named volumes arrive root-owned, and
  a file the runtime user cannot open fails with
  `Unable to open connection to local database file:…: 14`
  (`SQLITE_CANTOPEN`). The proof's app image fixes ownership before fpm
  starts; replicate that step if your platform mounts volumes root-owned.

## 5. The outage playbook (what the offline tiers actually do)

This is the behavior the proof stages with a real `docker network
disconnect` — known-good against a live sqld:

1. **While cut off**, the journaled flavor constructs and writes normally:
   each `execute()` lands in the local journal and bumps
   `getPendingOperationsCount()` by one (the proof saw `CREATE` → 1, then
   `REPLACE` → 2, 3, 4). `isOnline()` reports `false`.
2. **The journal outlives connections**: pending counts accumulated across
   separate opens/closes in the proof — the state you rely on after a crash
   mid-outage.
3. **A `sync()` during the outage fails with the byte-exact message**
   `Sync failed: Cannot sync: no internet connection` — same string
   `tests/e2e` locks. Alert on it, retry later, do not lose the state.
4. **When the server comes back**, the next flavor open **auto-drains the
   backlog** (pending → 0, `isOnline()` → yes). With a reachable server you
   cannot "hold" pending by not calling `sync()` — if you need a controlled
   sync window, control the network (or deploy the flavor only where the
   outage is the design).
5. **Server-blind separation is real**: during the proof's outage the
   server had **zero** of the journaled rows while the journal held them —
   readers on the server never saw half-written offline work, and the
   server's other tables kept serving (`kv` stayed at 50 rows).

Operational guardrails, calibrated:

* `syncInterval: 0` keeps background auto-sync off — `sync()` is explicit;
  nothing races your phases.
* `isOnline()` caches per connection for 5 seconds — a binding flip of your
  LB/VPN is observed within seconds, and two connections to different hosts
  never share that cache (upstream fix in this PR).
* On reconnect, prefer *observing the server* (`SELECT COUNT(*)` from a
  second client) over trusting the drain mechanism itself — the proof's
  `offline-sync` asserts server state, and so should your health endpoint.

## 6. Durability & restarts

Proven both ways in the reference run:

* **App restart** (`compose restart app` — the pod-crash analog): the file
  DB row written before the restart survived on its named volume; a fresh
  fpm worker came up healthy; the server was untouched.
* **Server restart**: the server's volume survived and the app reached it
  again through Hrana.

Checklist to keep those guarantees in your platform:

* Put the **app file DB + journal files** and the **sqld data dir** on
  persistent storage that outlives the container (named volumes / PVs).
* Restart the app process freely — per-request connection lifetime means
  there is nothing else to drain. If you run longer-lived workers or
  daemons, keep preferring explicit `close()`; the extension also cleans up
  on GC/drop (registry + finalized statements + abandoned transactions
  rolled back), which exists so a forgotten object is not forever.
* Restart sqld freely as well; the proof reconnects through the same DSN
  after the restart.

## 7. Concurrency discipline (calibrated, be deliberate here)

* The proof's workload is **sequential per worker** — that is the safe
  pattern. Two concurrent fpm processes writing one local file with no busy
  timeout collided instantly (`SQLite failure: "database is locked"`
  observed during calibration). If you must share a file across concurrent
  writers today, serialize the writes (single writer worker / queue) — a
  busy-timeout knob is not part of the extension surface yet; treat
  concurrent sharing as unsupported and track it upstream.
* In `offline_writes` mode, ways to corrupt the sync path are closed by
  design: journal-writing routes that would not replay (`prepare()`,
  `transaction()`) throw instead.
* Web requests share nothing: every request opens its own connections. No
  per-connection state (including the online cache) leaks across requests.

## 8. Memory & error-log hygiene

* **Leak guard**: the reference run's churn window measured **1540 kB**
  (warm) and **132 kB** (measured) of RSS around 200 open→write→read→close
  cycles inside one fpm worker against a generous 8192 kB bound. The old
  fail-state (~140 kB retained per connection cycle) would have blown past
  it. Re-run `proof.sh` whenever you change versions/infrastructure to keep
  the claim current.
* **Per-request RSS is meaningless** under fpm (each request lands on any
  pool worker — the proof observed even negative deltas doing that
  "naively"). If you measure leaks, measure inside one process.
* **The error log is opt-in now**: nothing is written unless you set
  `LIBSQL_PHP_ERROR_LOG=<path>`. During rollout, turn it on on a canary
  pod — and point it at a per-pod directory, never a shared volume: entries
  contain SQL text.
* **Catch `Throwable`, not only `Exception`**: engine failures throw
  `Exception`; argument-shape errors throw `ArgumentCountError` (an
  `Error`). A `catch (Exception)` alone misses the second family — see
  [error-semantics.md](error-semantics.md) for the full contract.

## 9. CDC / `captureIt` in production

Verified in this PR (full delivery semantics:
[LibSQL-class.md](LibSQL-class.md#capture-events-to-a-webhook-captureit)):

* an **empty `cdc_url` makes `captureIt()` return `false` without throwing**
* `null` throws a defined error
* delivery failures return `false`

Mind the property name: it is registered as `cdc_url` (before this PR it
silently registered as `cdcUrl`, breaking both the docs examples and
`captureIt()` configuration).

Boundary: the proof harness does not exercise CDC delivery. Until you have
your own probe for it, keep `captureIt()` off the critical write path.

## 10. Upgrades, versions & the proof as your gate

* Pin the libSQL server version you deploy. The proof was green against
  **sqld `0.24.33`** with extension **`1.6.2` / core `3.45.1-3045001`**
  (the pinned libsql crate resolved at `0.9.29` for the pinned `0.9.19`).
* **Before and after upgrading** the extension, the runtime host, the
  server, or your orchestration: run
  `docker/production-proof/proof.sh`. It rebuilds the extension from your
  actual source tree into the fpm image and starts from wiped volumes, so
  the result is deterministic; exit code > 0 blocks. Run it in CI when
  touching sync/offline paths or connection lifecycles (see
  [CONTRIBUTING.md](../CONTRIBUTING.md)).
* Air-gapped / registry-restricted environments: `compose.local-sqld.yaml`
  + `sqld.Dockerfile` swap the official image for a locally built `sqld`
  binary (copy yours in; it is gitignored, never committed).

## 11. What is NOT proven (read before you promise someone)

Said plainly, so nobody mistakes guidance for evidence:

1. **Long soak / heavy load** — the reference run is hundreds of requests;
   no multi-hour soak or throughput benchmark exists in this PR.
2. **Multi-replica topology** — multiple journaled replicas converging into
   one server concurrently is outside today's proof (single app ↔ single
   server).
3. **Encryption, CDC delivery, load-extension** — documented separately,
   not exercised by the proof harness.
4. **Turso Cloud β flavor** — different creation semantics (needs one
   reachable sync; `prepare()`/`transaction()` allowed there) — see
   [offline-writes-turso-connection.md](offline-writes-turso-connection.md)
   and the flavor matrix.
5. **ZTS / Windows / macOS** — supported per docs, unproven by the harness.

## 12. Pre-deploy checklist

- [ ] `docker/production-proof/proof.sh` green on the target sqld + PHP
      version (24/0, `PROOF:` JSON `all_ok:true`).
- [ ] The exact PHP API's `.so` shipped; `conf.d` ini for fpm + CLI; no
      `-d` flags.
- [ ] File DB + journal on persistent storage; runtime-user ownership
      bootstrapped where volumes arrive root-owned.
- [ ] Journaled flavor (if used): non-empty `authToken`; `syncInterval: 0`;
      no `prepare()`/`transaction()` on the write path; monitoring reads
      `getPendingOperationsCount()` / `isOnline()` / server-side row
      counts.
- [ ] Shared-file concurrency avoided or serialized; `database is locked`
      treated as an architecture signal, not a retry button.
- [ ] `LIBSQL_PHP_ERROR_LOG` on a canary pod (per-pod path).
- [ ] Handlers catch `Throwable`.
- [ ] Rollback plan: previous `.so` + server version; the proof gates every
      future upgrade too.
