# Proof of production usage (docker compose)

This harness runs the extension exactly like a packaged deployment would:

* PHP **8.5-FPM** with this PR's extension compiled in **release mode** and
  installed the packaged way (`.so` in the extension dir + a `conf.d` ini
  drop-in — not `-d extension=` flags),
* behind **nginx**, the standard web tier,
* against a **live libSQL server** (`ghcr.io/tursodatabase/libsql-server`),
* with a small workload app whose every request opens/closes its connections
  in the flavor the action needs (local file / remote Hrana / journaled
  offline writer),
* driven by a runner that asserts observed behavior and emits
  `PROOF: {...}` — the runner itself is *also an independent sqld client*, so
  offline→sync→visibility is proven from two different processes.

```
                 proof.sh (restarts + outage window between phases)
                              │
              ┌───────────────┼───────────────┐
              ▼               ▼               ▼
        ┌──────────┐   ┌──────────┐   ┌────────────┐
        │   web    │   │   app    │   │    sqld    │
        │  nginx   │──▶│ php-fpm  │──▶│  libSQL    │
        │  :80     │   │  :9000   │   │ server :8080│
        └──────────┘   └────┬─────┘   └────────────┘
                            │   volume: app-data (file DB + offline journal)
                            ▼        volume: sqld-data (server DB)
        proof (runner) ▶ nginx, and ▶ sqld directly (second client)
```

Networks are split (`web-net` for nginx↔app, `server-net` for app↔sqld),
so the outage window cuts only `server-net` — the public path keeps
serving traffic while the app cannot reach the server.

## Run it

```bash
docker/production-proof/proof.sh                 # full proof, one pass
docker/production-proof/proof.sh --teardown      # same, then `down -v`
```

Phases you can also run manually (in this exact order, or let `proof.sh`
do the choreography). `--no-deps` matters: a plain `docker compose run`
reconciles the running services' network config — reattaching the app would
silently undo the outage window:

```bash
docker compose -f docker/production-proof/compose.yaml up -d --build

docker compose -f docker/production-proof/compose.yaml run --rm --no-deps proof main

# outage window: cut app ↔ sqld, then reconnect after the phase
docker network disconnect production-proof_server-net "$(docker compose -f docker/production-proof/compose.yaml ps -q app)"
docker compose -f docker/production-proof/compose.yaml run --rm --no-deps proof offline-write
docker network connect production-proof_server-net "$(docker compose -f docker/production-proof/compose.yaml ps -q app)"
docker compose -f docker/production-proof/compose.yaml run --rm --no-deps proof offline-sync

# restarts + durability
docker compose -f docker/production-proof/compose.yaml restart app
docker compose -f docker/production-proof/compose.yaml run --rm --no-deps proof persist
docker compose -f docker/production-proof/compose.yaml restart sqld
docker compose -f docker/production-proof/compose.yaml run --rm --no-deps proof persist-server

docker compose -f docker/production-proof/compose.yaml run --rm --no-deps proof report
```

Reports: `proof-report/report-*.json` per phase, and the merged
`proof-report/full-report.json` + a final one-line `PROOF: {...}`.

## What each step proves (claim → assert map)

| # | Production claim | Where it is asserted |
| - | ---------------- | -------------------- |
| 1 | "It installs like an extension, not a hack" | `ext_version` served by fpm with the ini from `conf.d` — no `-d` flags anywhere (phase `main`, health) |
| 2 | "Remote writes work through a real web tier" | 50 Hrana writes via nginx→fpm→sqld; row count grows 1..50 (phase `main`) |
| 3 | "Data is visible to other consumers of the server" | the *runner's own* second Hrana client reads all rows directly from sqld (phase `main`) |
| 4 | "The journaled flavor behaves like production during an outage" | `proof.sh` disconnects app↔sqld (server-net only; nginx keeps serving); offline-flavor constructs while `isOnline=false`, CREATE is journaled (pending==1), pending grows per statement across separate reopened connections — the journal outlives the connection (phase `offline-write`) |
| 5 | "Offline writes do not leak partial data" | during the outage sqld has **zero** `jour` rows while the local journal holds them; the outage is surgical — the server's other tables are unaffected (phase `offline-write`) |
| 6 | "sync()/drain actually replicates" | after reconnect the second client sees the jour rows on the server, key-by-value exact, nothing duplicated (phase `offline-sync`) |
| 7 | "App process restarts are safe" | `compose restart app` → file DB seed row intact, fresh fpm healthy (phase `persist`) |
| 8 | "Server restarts are safe" | `compose restart sqld` → volume data survived; the app reads it again (phase `persist-server`) |
| 9 | "No leak while serving requests" | in-process churn: 200 open→write→read→close cycles inside ONE fpm worker, RSS delta around them bounded — per-request RSS readings are meaningless under the fpm pool (phase `main`) |

## Topology files

| File | Role |
| ---- | ---- |
| `compose.yaml` | the 4-tier production topology (web/app/sqld/proof) |
| `Dockerfile` | release-build of the extension + php-fpm app (multi-stage; build context is the repo root, so the artifact comes from the reviewed source) |
| `app/index.php` | the workload payloads (local/remote/offline flavors) |
| `default.conf` | nginx tier (fpm upstream + front controller) |
| `run-proof.php` | the runner: steps, asserts, phase reports, final PROOF JSON |
| `proof.sh` | orchestrator incl. the restart choreography |
| `compose.local-sqld.yaml` | optional: run the local `~/.turso/sqld` binary instead of the official image (air-gapped environments) |
| `sqld.Dockerfile` + `sqld-entrypoint.sh` | build pieces for that fallback (your local `sqld` binary is copied in, gitignored, never committed) |

## Results grid (update alongside runs)

Verified locally (Docker 29.9.3 / Compose v5.7.0, Linux Mint 21.3 on x86-64;
sqld `0.24.33`; PHP `8.5.12` as `fpm-fcgi`; extension `1.6.2`, core
`3.45.1-3045001`; every run starts from wiped volumes):

| Phase | Result | Asserts | Notes |
| ----- | ------ | ------- | ----- |
| main | ✅ pass | 9/9 | readiness ×2, health, setup, 50 remote writes (count 1..50), exact read-back, cross-client, seed, leak guard (churn warm 1540 kB / measured 132 kB vs 8192 kB bound) |
| offline-write | ✅ pass | 5/5 | outage honored (`isOnline=false`), init pending==1, 3 journaled writes pending 1→4, journal persists, sqld blind while server stands (kv intact) |
| offline-sync | ✅ pass | 3/3 | reconnected: online again, journal drained (pending==0), second client reads jour rows back key+value exact, nothing duplicated |
| persist | ✅ pass | 4/4 | `restart app`: seed survived (volume), fresh fpm worker healthy, server unaffected (still 50) |
| persist-server | ✅ pass | 3/3 | `restart sqld`: server volume data survived, app reached the restarted server (last row exact) |
| **TOTAL** | ✅ **pass** | **24/0** | `PROOF: {"ok":24,"fail":0,"all_ok":true}` — exit 0 |

## Integrity notes

* The build context is the **repository root**, so the compiled `.so` inside
  the app image is produced from exactly the tree being reviewed — the proof
  and the PR are the same artifact. `.dockerignore` (repo root) keeps
  `target/`, `vendor/`, `docker/`, `tests/`, `docs/` and `php-server/` out of
  the context (with an exception for the app payload the image bakes in), so
  editing harness files doesn't bust the Rust layer cache.
* The sqld data and the app DB both live on **named volumes**; `--teardown`
  (`down -v`) is the only thing that erases them. Every run *starts* from
  wiped volumes (`down -v`), so nothing from a previous run can leak into
  the next proof.
* The runner never touches the docker daemon — restarts and the outage
  window's network disconnect happen from the host-side `proof.sh`
  *between* phases, so every assert runs inside a realistic steady state.
* The official libSQL server image ships no network tools and its shell is
  dash — a compose `healthcheck` cannot probe it with `/dev/tcp`; readiness
  is instead polled by the runner with a direct Hrana `SELECT 1` and by a
  health request to the web tier.
* The journaled offline flavor needs a **non-empty** `authToken` (an empty
  one yields "Pending operations only available in offline_write mode"),
  and with a *reachable* server it auto-drains its backlog on open — which
  is why the offline story is staged through a real network outage
  (`docker network disconnect` on server-net) rather than "just don't call
  sync()". `syncInterval: 0` keeps auto-sync off, so nothing races the
  explicit phases. Calibration additionally locked the outage failure
  mode: a direct `sync()` while disconnected errors with the byte-exact
  `Sync failed: Cannot sync: no internet connection` — the same message
  `tests/e2e` proves.
* Phase invocations pass `--no-deps`: a plain `docker compose run`
  reconciles the compose model for running services — it re-attaches the
  app to server-net and silently undoes the outage window. On fresh
  volumes, "no such table" from a direct-client check reads as **zero
  rows** (the table only comes into existence when the first replica
  syncs) — the asserts treat absent-as-zero intentionally.
* Concurrency note from calibration: two fpm writers hitting one local file
  without a busy timeout collide immediately ("database is locked"); the
  runner's workload is sequential and never overlaps, but production apps
  that share a file across concurrent workers should set a busy timeout /
  retry policy.
* Leak threshold is calibrated from observed fpm behavior (cold worker
  first-touch ≤ ~5.8 MB; churn deltas in a verified run: 1540 kB warm,
  132 kB measured) and re-documented whenever the environment changes; it
  is generous by design and exists to catch *the old fail-state* (a leak
  near ~140 kB per connection cycle), not to be a precise detector.
