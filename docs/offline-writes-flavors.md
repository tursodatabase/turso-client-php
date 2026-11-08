# Offline Writes: Choosing and Comparing the Two Flavors

LibSQL PHP supports two different **offline-write flavors**: a self-hosted
one built around a **local write journal** (the `sqld` flavor) and one built
on libSQL's synced databases against **Turso Cloud**. They solve the same
problem — *keep working while the network is down, replay writes later* —
but they differ in how writes are captured, which APIs are available, and
what a `sync()` actually does.

This page compares them side by side. Detailed walkthroughs live in each
flavor's own page:

* [Offline Writes with sqld Connection](offline-writes-sqld-connection.md) — the journaled flavor
* [Offline Writes with Turso Connection](offline-writes-turso-connection.md) — the Turso Cloud flavor

---

## The two flavors at a glance

| | **Journaled** (sqld flavor) | **Turso Cloud** (beta flavor) |
| --- | --- | --- |
| Activation flags | `sqld_offline_mode: true` + `offline_writes: true` | `sqld_offline_mode: false` + `offline_writes: true` |
| Internal mode | `offline_write` | `remote_replica` |
| Where writes go | local write journal (pending operations), replayed by `sync()` | libSQL synced local database, pushed in the background |
| Constructible with no network | ✅ yes — auto-initializes the local file | ❌ no — the remote must be reachable at construction |
| Works only against | any reachable `sqld`/libsql-server | a real Turso/libsql-server endpoint (Hrana protocol) |
| `prepare()` / `transaction()` | ❌ throw (would bypass the journal) | ✅ work |
| Connectivity APIs (`checkConnectivity()`/`isOnline()`/`getPendingOperationsCount()`) | ✅ work | ❌ throw (managed by libSQL background sync) |

All rows above are observable behavior: flavor construction, the dead-port
requirements, and the gated calls were each exercised against the extension
with a dead port (`127.0.0.1:9`) and a live non-libsql HTTP server, and the
results quoted below are exactly what the engine returned.

---

## Construction recipes

Both flavors share the same config array and constructor; only the flags
differ. Every parameter after `$config` must be passed explicitly (the
extension cannot fill in skipped optional slots):

**Journaled flavor:**

```php
$config = [
    "url"       => "file:sqld-offline-write.db", // local replica file
    "authToken" => "your_auth_token",
    "syncUrl"   => "http://vortexdb.your-own-server.com", // remote sqld
];

$db = new LibSQL(
    config: $config,
    sqld_offline_mode: true,  // journaled flavor
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true
);
```

**Turso Cloud flavor:**

```php
$config = [
    "url"       => "file:database.db",     // local synced replica file
    "authToken" => getenv("TURSO_AUTH_TOKEN"),
    "syncUrl"   => getenv("TURSO_DB_URL"),  // remote Turso endpoint
];

$db = new LibSQL(
    config: $config,
    sqld_offline_mode: false, // Turso Cloud flavor
    flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
    encryption_key: "",
    offline_writes: true
);
```

### What happens at construction time (verification results)

| Construction scenario | Journaled | Turso Cloud (beta) | Plain replica (`offline_writes: false`) |
| --- | --- | --- | --- |
| Remote port dead (`127.0.0.1:9`) | ✅ OK — journals locally, file auto-initialized | ❌ throws `Offline-write connection failed: sync error: http dispatch error: error trying to connect` | ❌ throws `Replica database build failed: sync error: ... tcp connect error` |
| Remote reachable but **not** a libsql server (HTTP 404) | ✅ OK | ❌ throws `Offline-write connection failed: sync error: failed to pull db export: status=404 ...` | ✅ OK at construction — first write fails with `Execution error: Write delegation: ... Invalid header bit 60 ...` |
| `syncUrl` missing from config | ✅ constructs, but **degrades to a plain local database** (no journal — see below) | ✅ constructs, degrades to plain local | ✅ constructs (plain local) |

Two consequences worth knowing before you pick a flavor:

* Only the **journaled** flavor can be constructed without any network
  present. This is the flavor for truly offline-first PHP processes.
* The Turso Cloud flavor verifies the remote **at construction time**
  (it pulls the database export), so a wrong URL or an HTTP endpoint that
  is not a libsql server surfaces immediately — before any writes.

### The degraded "plain local" shape

Opening a connection with any of the three recipes above *without* `syncUrl`
still succeeds — but none of the offline machinery activates (`syncUrl` is
what promotes the config into a replica/offline mode). The resulting
connection is a plain local database:

* `getPendingOperationsCount()` throws
  `Pending operations only available in offline_write mode`
* `sync()` throws `local mode does not support sync`

---

## API support matrix

Verified behaviors per connection shape:

| API | Journaled | Turso Cloud (beta) | Plain local / plain replica |
| --- | --- | --- | --- |
| `execute()`, `query()` | ✅ — writes land in the local journal; reads come back locally | ✅ — buffered locally, pushed in the background | ✅ |
| `prepare()` | ❌ throws — prepared statements would run on the underlying connection and bypass the journal | ✅ | ✅ |
| `transaction()` | ❌ throws — `BEGIN`/`COMMIT` are not captured as pending operations | ✅ | ✅ |
| `checkConnectivity()` | ✅ — HTTP GET to the exact `syncUrl` host; **any** response (even 404) counts as reachable | ❌ throws `Connectivity check only available in offline_write mode` | ❌ same throw |
| `isOnline()` | ✅ — same probe, cached for 5 seconds | ❌ throws `Online status check only available in offline_write mode` | ❌ same throw |
| `getPendingOperationsCount()` | ✅ — number of journaled operations not yet replayed | ❌ throws `Pending operations only available in offline_write mode` | ❌ same throw |
| `sync()` | ✅ manual replay — see table below | ✅ libSQL-embedded sync | ✅ (replica) / ❌ `local mode does not support sync` (local) |
| `totalChanges()`, `lastInsertedId()`, `isAutocommit()` | ✅ — reading the local replica | ✅ | ✅ |
| `close()` | ✅ removes the offline connection from the registry | ✅ | ✅ |

---

## Sync semantics per flavor

| Scenario | Journaled | Turso Cloud / plain replica |
| --- | --- | --- |
| Network down (`isOnline()` false) | throws `Sync failed: Cannot sync: no internet connection` — **pending operations are preserved**, try again later | throws `Sync failed: <connection error>` |
| Reachable HTTP endpoint that is not a libsql server | **returns without error** — but nothing is effectively replayed: pending operations stay queued (errors are logged, operations re-queued) — verify with `getPendingOperationsCount()` | fails at first use with an Hrana protocol error |
| Real server, sync succeeds | returns `Ok`; with `sync($log_info = true)` prints `Sync result: Synced N operations, M remaining` | returns, remote is current |

Notes on the journaled flavor's connectivity checks:

* `checkConnectivity()` and `isOnline()` probe the **exact host** in
  `syncUrl` — no hostname rewriting. A database that is down does not get
  answered by the platform's apex domain.
* "Online" means *an HTTP server answered the probe*, not *this host speaks
  libsql*. A 404 response counts as online.
* The probe has a 20-second timeout; expect the call to block on silently
  dropping hosts.
* `isOnline()` caches its answer for 5 seconds per connection, so two
  offline-write connections to different hosts never pollute each other's
  status.

For the seasoned pattern of choosing when to sync, both flavors tolerate the
same shape of user code (`try/catch` around `sync()`), but the journaled
flavor additionally exposes the queue length, which makes
`getPendingOperationsCount() > 0` a usable trigger for retry loops.

---

## Error text reference

Exact messages observed from the engine (useful for matching in test
suites):

| Message | Flavor / shape | When |
| --- | --- | --- |
| `prepare() is not supported in offline_write mode: prepared statements would bypass the offline write journal; use execute() or query() with bound parameters instead` | Journaled | Calling `prepare()` |
| `transaction() is not supported in offline_write mode: transactions would bypass the offline write journal; use execute() with bound parameters instead` | Journaled | Calling `transaction()` |
| `Connectivity check only available in offline_write mode` | Any non-journaled shape | `checkConnectivity()` |
| `Online status check only available in offline_write mode` | Any non-journaled shape | `isOnline()` |
| `Pending operations only available in offline_write mode` | Any non-journaled shape | `getPendingOperationsCount()` |
| `local mode does not support sync` | Plain local | `sync()` on a config without `syncUrl` |
| `Sync failed: Cannot sync: no internet connection` | Journaled | `sync()` while `isOnline()` is false |
| `Offline connection not found` | Journaled | Interior state lost (e.g. after `close()`) — retry with a fresh connection |
| `Offline-write connection failed: ...` | Turso Cloud | Construction against an unreachable or non-libsql remote |

---

## Next Steps

* 👉 [Offline Writes with sqld Connection](offline-writes-sqld-connection.md) — full journaled-flavor walkthrough
* 👉 [Offline Writes with Turso Connection](offline-writes-turso-connection.md) — full Turso Cloud walkthrough
* 👉 [Core API](LibSQL-class.md) — the `sync()`/connectivity method surface
* 👉 [LibSQLTransaction Class](LibSQLTransaction-class.md) — why journal-bypassing calls throw in the journaled flavor
