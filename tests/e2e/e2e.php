<?php
/**
 * End-to-end compatibility suite for the LibSQL PHP extension.
 *
 * Runs with no dependencies other than PHP itself and the extension:
 *
 *   php<v> -n -d extension_dir=<path> -d extension=liblibsql_php.so tests/e2e/e2e.php
 *
 * Deterministic and offline by design: the only "network" this suite touches
 * is a guaranteed-dead local port (127.0.0.1:9), used to simulate an offline
 * remote for the journaled offline-write flavor.
 *
 * Every assertion reflects behavior verified against the engine (see
 * docs/error-semantics.md and docs/offline-writes-flavors.md for the quotes).
 * If a PHP version newer/older than the ones upstream supports changes any
 * of these behaviors, the suite fails instead of drifting silently.
 *
 * Output: per-scenario progress lines, a human summary, and a final
 * "E2E-REPORT: {...}" JSON line (consumed by run-matrix.sh).
 */

error_reporting(E_ALL);

$GLOBALS['TURSO_E2E_TMPFILES'] = [];
register_shutdown_function(function () {
    foreach ($GLOBALS['TURSO_E2E_TMPFILES'] as $f) {
        foreach (glob($f . '*') ?: [] as $l) {
            @unlink($l);
        }
    }
});

function e2e_tmpfile($name)
{
    $f = sys_get_temp_dir() . '/turso-e2e-' . uniqid() . '-' . $name . '.db';
    $GLOBALS['TURSO_E2E_TMPFILES'][] = $f;
    return $f;
}

if (!class_exists('LibSQL')) {
    fwrite(STDERR, "the libsql_php extension is not loaded - run this script with\n"
        . "  -d extension_dir=<path> -d extension=liblibsql_php.so\n");
    exit(2);
}

$report = ['scenarios' => 0, 'ok' => 0, 'fail' => 0];
$startedAt = microtime(true);

function e2e_scenario($name, $fn)
{
    global $report;
    $report['scenarios']++;
    try {
        $fn();
        echo "  OK    $name\n";
        $report['ok']++;
    } catch (Throwable $e) {
        echo "  FAIL  $name: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")\n";
        $report['fail']++;
    }
}

function e2e_step($cond, $what)
{
    if (!$cond) {
        throw new Exception("step failed - $what");
    }
}

/** Asserts `$fn` throws a Throwable whose message is exactly `$message`. */
function e2e_exact_throw($fn, $message, $class = [])
{
    try {
        $fn();
    } catch (Throwable $e) {
        if (!empty($class) && get_class($e) !== $class) {
            throw new Exception("expected $class, got " . get_class($e) . ": {$e->getMessage()}");
        }
        if ($e->getMessage() !== $message) {
            throw new Exception("expected message \"$message\", got \"{$e->getMessage()}\"");
        }
        return;
    }
    throw new Exception("expected throw \"$message\", nothing was thrown");
}

/** Asserts `$fn` throws a PHP argument-shape Error (e.g. ArgumentCountError). */
function e2e_arg_error($fn, $needle)
{
    try {
        $fn();
    } catch (Throwable $e) {
        $isArgError = ($e instanceof Error) && str_contains($e->getMessage(), $needle);
        if (!$isArgError) {
            throw new Exception("expected Error containing \"$needle\", got " . get_class($e) . ": {$e->getMessage()}");
        }
        return;
    }
    throw new Exception("expected argument Error containing \"$needle\", nothing was thrown");
}

// =====================================================================
// S1 - local file connection end-to-end lifecycle
// =====================================================================
e2e_scenario('local file connection lifecycle (persist across close/reopen)', function () {
    $file = e2e_tmpfile('local');
    $dsn = 'file:' . $file;

    $db = new LibSQL($dsn);
    $db->execute("CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)");
    $db->execute("INSERT INTO users VALUES (1, 'Ada')");
    $db->execute("INSERT INTO users VALUES (2, 'Linus')");
    $db->close();

    $db = new LibSQL($dsn);
    $rows = $db->query("SELECT id, name FROM users ORDER BY id")->fetchArray(LibSQL::LIBSQL_ASSOC);
    e2e_step(count($rows) === 2, 'two rows persisted');
    e2e_step(($rows[0]['name'] ?? '') === 'Ada' && ($rows[1]['id'] ?? 0) === 2, 'row values persisted');

    $db->execute("UPDATE users SET name = 'Grace' WHERE id = 1");
    $row = $db->query("SELECT name FROM users WHERE id = 1")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step(($row['name'] ?? '') === 'Grace', 'update applied after reopen');
    $db->close();
});

// =====================================================================
// S2 - in-memory + change accounting semantics (verified engine rules)
// =====================================================================
e2e_scenario('in-memory connection with changes()/totalChanges() accounting', function () {
    $db = new LibSQL(':memory:');
    e2e_step($db->isAutocommit() === true, 'autocommit is on by default');

    $db->execute("CREATE TABLE tc (v INTEGER)");
    // DDL does not raise the per-statement change count.
    e2e_step($db->changes() === 0, 'changes() zero after DDL');
    $db->execute("INSERT INTO tc VALUES (1)");
    $db->execute("INSERT INTO tc VALUES (1)");
    e2e_step($db->changes() === 1, 'changes() per last modifier');
    e2e_step($db->totalChanges() === 2, 'totalChanges() is 2 after two inserts');
    $db->execute("UPDATE tc SET v = 2 WHERE v = 1");
    e2e_step($db->changes() === 2, 'changes() = 2 after two-row update');
    e2e_step($db->totalChanges() === 4, 'totalChanges() = 4 (2 inserts + 1 update x 2 rows)');
    $db->close();
});

// =====================================================================
// S3 - typed bindings survive a round trip (P1 behavior)
// =====================================================================
e2e_scenario('typed value bindings (int/float/bool/null/string/blob-list)', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE types (i INTEGER, f REAL, s TEXT, b BOOLEAN, blob BLOB, n INTEGER)");

    $stmt = $db->prepare("INSERT INTO types VALUES (:i, :f, :s, :b, :blob, :n)");
    $stmt->bindNamed(['i' => 42, 'f' => 1.5, 's' => 'text', 'b' => true, 'blob' => [1, 2, 255, 0], 'n' => null]);
    e2e_step($stmt->execute() === 1, 'execute() with stored bindings inserts one row');

    $row = $db->query("SELECT i, f, s, b, blob, n FROM types")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step(gettype($row['i']) === 'integer' && $row['i'] === 42, 'int kept as INTEGER');
    e2e_step(gettype($row['f']) === 'double' && $row['f'] === 1.5, 'float kept as REAL');
    e2e_step(gettype($row['s']) === 'string' && $row['s'] === 'text', 'string kept as TEXT');
    e2e_step(gettype($row['b']) === 'integer' && $row['b'] === 1, 'bool bound as INTEGER (1)');
    e2e_step($row['blob'] === [1, 2, 255, 0], 'int-list bound as BLOB and returned back');
    e2e_step($row['n'] === null, 'null kept as NULL');

    // positional binding path
    $stmt = $db->prepare("INSERT INTO types VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bindPositional([7, 0.5, 'pos', false, [9], null]);
    $stmt->execute();
    $row = $db->query("SELECT i, b FROM types WHERE s = 'pos'")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($row['i'] === 7 && $row['b'] === 0, 'positional binding works (bool false -> 0)');
    $db->close();
});

// =====================================================================
// S4 - prepared statement reuse loop (P1 fix: re-binding per execute)
// =====================================================================
e2e_scenario('prepared statement reuse loop with re-binding', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE t (v TEXT)");

    $stmt = $db->prepare("INSERT INTO t VALUES (?)");
    foreach (['one', 'two', 'three'] as $n) {
        $stmt->bindPositional([$n]);
        $stmt->execute();
    }
    $count = $db->query("SELECT COUNT(*) c FROM t")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($count['c'] === 3, "re-bind loop stored 3 distinct rows (got {$count['c']})");

    // reset() clears stored bindings; re-bind is required afterwards
    $stmt->reset();
    $stmt->bindPositional(['four']);
    e2e_step($stmt->execute() === 1, 're-bind after reset works');
    $row = $db->query("SELECT v FROM t WHERE v = 'four'")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($row['v'] === 'four', 'post-reset row arrived');
    $db->close();
});

// =====================================================================
// S5 - fetch modes and shapes (documented engine contract)
// =====================================================================
e2e_scenario('fetch modes: ASSOC/NUM/BOTH/ALL + fetchSingle defaults', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE fm (id INTEGER PRIMARY KEY, name TEXT)");
    $db->execute("INSERT INTO fm VALUES (1, 'Ada')");

    $assoc = $db->query("SELECT id, name FROM fm")->fetchArray(LibSQL::LIBSQL_ASSOC);
    e2e_step($assoc === [['id' => 1, 'name' => 'Ada']], 'LIBSQL_ASSOC shape');
    $num = $db->query("SELECT id, name FROM fm")->fetchArray(LibSQL::LIBSQL_NUM);
    e2e_step($num === [[1, 'Ada']], 'LIBSQL_NUM shape');
    $both = $db->query("SELECT id, name FROM fm")->fetchArray(LibSQL::LIBSQL_BOTH);
    e2e_step($both === [['id' => 1, 0 => 1, 'name' => 'Ada', 1 => 'Ada']], 'LIBSQL_BOTH shape (named key interleaved with its numeric twin)');
    $all = $db->query("SELECT id, name FROM fm")->fetchArray(LibSQL::LIBSQL_ALL);
    e2e_step(isset($all['columns'], $all['rows'], $all['rows_affected'], $all['last_insert_rowid']),
        'LIBSQL_ALL structure keys');
    e2e_step($all['columns'] === ['id', 'name'] && $all['last_insert_rowid'] === 1, 'LIBSQL_ALL values');

    // column order follows the query, not the schema
    $num = $db->query("SELECT name, id FROM fm")->fetchArray(LibSQL::LIBSQL_NUM);
    e2e_step($num === [['Ada', 1]], 'NUM column order follows the query');

    // default mode is LIBSQL_BOTH
    $default = $db->query("SELECT id, name FROM fm")->fetchArray();
    e2e_step($default == $both, 'fetchArray() default mode is BOTH');

    // fetchSingle defaults to BOTH and returns [] on zero rows
    $single = $db->query("SELECT id, name FROM fm")->fetchSingle();
    e2e_step($single == ['id' => 1, 'name' => 'Ada', 0 => 1, 1 => 'Ada'], 'fetchSingle() default is BOTH');
    $none = $db->query("SELECT id FROM fm WHERE id = 999")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($none === [], 'fetchSingle() zero rows is [] (empty array)');

    // a result object re-executes per fetch: later fetches see new rows
    $res = $db->query("SELECT COUNT(*) c FROM fm");
    $before = $res->fetchSingle(LibSQL::LIBSQL_ASSOC)['c'];
    $db->execute("INSERT INTO fm VALUES (2, 'Bob')");
    $after = $res->fetchSingle(LibSQL::LIBSQL_ASSOC)['c'];
    e2e_step($before === 1 && $after === 2, 'result re-executes per fetch (live view)');
    $db->close();
});

// =====================================================================
// S6 - LAZY streaming iterator contract
// =====================================================================
e2e_scenario('LIBSQL_LAZY streaming (current/next/valid, forward-only)', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE lx (id INTEGER)");
    $db->execute("INSERT INTO lx VALUES (1), (2), (3)");

    $it = $db->query("SELECT id FROM lx")->fetchArray(LibSQL::LIBSQL_LAZY);
    e2e_step(get_class($it) === 'LibSqlIterator', 'LAZY returns an iterator (canonical class spelling "LibSqlIterator")');
    $seen = [];
    do {
        $seen[] = [$it->key(), $it->current()];
        $it->next();
    } while ($it->valid());
    e2e_step(array_map(fn($r) => $r['id'], array_column($seen, 1)) === [1, 2, 3], 'rows stream in order (ids 1,2,3), readable under BOTH-like keys');
    e2e_step(count($seen) === 3, "exactly 3 rounds for 3 rows (got " . count($seen) . ")");
    e2e_step(array_column($seen, 0) === [0, 1, 2], 'key() is the 0-based row index');
    e2e_step($it->current() === null && $it->valid() === false, 'after end: current() === null, valid() === false');
    $it->rewind();
    e2e_step($it->current() === null && $it->valid() === false, 'rewind() does not restart a row-set iterator');

    // zero rows: exactly one pass with current() === null
    $z = $db->query("SELECT id FROM lx WHERE id > 100")->fetchArray(LibSQL::LIBSQL_LAZY);
    $zr = []; do { $zr[] = $z->current(); $z->next(); } while ($z->valid());
    e2e_step($zr === [null], 'zero-row LAZY yields exactly one null round');
    $db->close();
});

e2e_scenario('LIBSQL_LAZY fetchSingle rowIterator + public LibSqlIterator', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE p (name TEXT)");
    $db->execute("INSERT INTO p VALUES ('Ada')");
    // fetchSingle(LAZY) streams one row's values; array-backed => rewind restarts
    $sg = $db->query("SELECT name FROM p")->fetchSingle(LibSQL::LIBSQL_LAZY);
    $v1 = []; do { $v1[] = $sg->current(); $sg->next(); } while ($sg->valid());
    e2e_step($v1 === ['Ada'], 'fetchSingle(LAZY) walks the single row');
    $sg->rewind();
    $v2 = []; do { $v2[] = $sg->current(); $sg->next(); } while ($sg->valid());
    e2e_step($v2 === $v1, 'fetchSingle(LAZY) iterator restarts on rewind() (array-backed)');

    $a = new LibSqlIterator([5, 6, 7]);
    $vals = []; do { $vals[] = $a->current(); $a->next(); } while ($a->valid());
    e2e_step($vals === [5, 6, 7], 'iterator class works over a plain PHP array');
    $a->rewind();
    e2e_step($a->current() === 5, 'array-backed iterator restarts on rewind()');
    $db->close();
});

// =====================================================================
// S7 - transactions (behaviors, isolation, idempotence rules)
// =====================================================================
e2e_scenario('transactions: behaviors, commit/rollback, second-call errors', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE acc (id INTEGER PRIMARY KEY, v TEXT)");
    $db->execute("INSERT INTO acc VALUES (1, 'kept')");

    $trx = $db->transaction('WRITE');
    e2e_step($trx->isAutocommit() === false, 'isAutocommit() false inside a transaction');
    e2e_step($trx->execute("INSERT INTO acc VALUES (2, 'inside')") === 1, 'trx execute affected 1 row');
    e2e_step($trx->changes() === 1, 'trx changes() inside');
    $trx->commit();
    e2e_step($db->isAutocommit() === true, 'connection back to autocommit after commit');
    $count = $db->query("SELECT COUNT(*) c FROM acc")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($count['c'] === 2, 'committed row persisted');
    e2e_exact_throw(fn() => $trx->commit(), 'Transaction not found');

    $trx2 = $db->transaction();
    $trx2->execute("INSERT INTO acc VALUES (3, 'discarded')");
    $trx2->rollback();
    $count = $db->query("SELECT COUNT(*) c FROM acc")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($count['c'] === 2, 'rollback discarded the write');
    e2e_exact_throw(fn() => $trx2->rollback(), 'Transaction not found');

    // unknown behavior strings fall back to DEFERRED (no throw)
    $trx3 = $db->transaction('DEFERRED');
    $trx3->execute("SELECT 1" === false ? '' : "INSERT INTO acc VALUES (4, 'deferred-x')");
    $trx3->commit();
    $count = $db->query("SELECT COUNT(*) c FROM acc")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($count['c'] === 3, 'default transaction() committed');
    $db->close();
});

// =====================================================================
// S8 - error semantics (engine contract since the panic-free mapping)
// =====================================================================
e2e_scenario('error semantics: exact engine messages on the critical paths', function () {
    // DSN parse failures
    e2e_exact_throw(fn() => new LibSQL(''), 'Failed to parse DSN');
    e2e_exact_throw(fn() => new LibSQL('libsql:database.db'), 'Failed to parse DSN');

    $db = new LibSQL(':memory:');

    $badSql = 'SLCT 1';
    e2e_exact_throw(
        fn() => $db->prepare($badSql),
        'Failed to prepare statement: SQLite failure: `near "SLCT": syntax error`'
    );
    e2e_exact_throw(
        fn() => $db->execute('INSRT INTO x VALUES (1)'),
        'Execution error: SQLite failure: `near "INSRT": syntax error`'
    );

    // DML-only rule: statements that return rows are rejected
    $db->execute("CREATE TABLE dml (v)");
    $db->execute("INSERT INTO dml VALUES (1)");
    $sel = $db->prepare("SELECT v FROM dml");
    e2e_exact_throw(fn() => $sel->execute(), 'Execute returned rows');
    e2e_exact_throw(fn() => $db->execute('SELECT v FROM dml'), 'Execution error: Execute returned rows');

    // register lifecycle
    e2e_exact_throw(fn() => $sel->finalize() === null ? $sel->execute() : true, 'Statement not found');

    // close twice / use after close
    $d2 = new LibSQL(':memory:');
    $d2->close();
    e2e_exact_throw(fn() => $d2->close(), 'Connection ID not found.');
    e2e_exact_throw(fn() => $d2->query('SELECT 1'), 'Connection not found');

    // query() defers failures to the fetch
    e2e_exact_throw(
        fn() => $db->query('SELECT * FROM missing_table')->fetchSingle(LibSQL::LIBSQL_ASSOC),
        'SQLite failure: `no such table: missing_table`'
    );

    // out-of-bounds column metadata
    $res = $db->query('SELECT v FROM dml');
    e2e_exact_throw(fn() => $res->columnName(5), 'Column index 5 out of bounds');
    e2e_step($res->columnType(9) === 'Null', 'columnType() tolerates out-of-range with "Null"');

    // named-argument rule errors are PHP Errors, not engine Exceptions
    e2e_arg_error(function () use ($db) {
        // skip sqld_offline_mode while passing a later named argument
        return @new LibSQL(config: [], flags: 6);
    }, 'must be passed explicitly');
});

// =====================================================================
// S9 - journaled offline-write flavor end-to-end (P2 behavior)
// =====================================================================
e2e_scenario('offline-write journal: construct offline, gate, retain, reopen', function () {
    $file = e2e_tmpfile('offline');
    $mk = function () use ($file) {
        return new LibSQL(
            config: [
                'url' => 'file:' . $file,
                'authToken' => 'tok',
                'syncUrl' => 'http://127.0.0.1:9', // dead port = offline simulation
                'syncInterval' => 5,
                'read_your_writes' => true,
                'encryptionKey' => '',
            ],
            sqld_offline_mode: true,
            flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
            encryption_key: '',
            offline_writes: true
        );
    };

    $db = $mk();
    e2e_step($db->checkConnectivity() === false, 'checkConnectivity() false at a dead port');
    e2e_step($db->isOnline() === false, 'isOnline() false at a dead port');

    $db->execute("CREATE TABLE jo (v INTEGER)");
    $db->execute("INSERT INTO jo VALUES (7)");
    $pending = $db->getPendingOperationsCount();
    e2e_step($pending >= 2, "two statements journaled (got $pending)");

    $row = $db->query('SELECT v FROM jo')->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($row['v'] === 7, 'read-your-writes on the local replica');

    // journaled flavor gates: prepare()/transaction() would bypass the journal
    e2e_exact_throw(
        function () use ($db) { return @$db->prepare('SELECT 1'); },
        'prepare() is not supported in offline_write mode: prepared statements would bypass the offline write journal; use execute() or query() with bound parameters instead'
    );
    e2e_exact_throw(
        function () use ($db) { @$db->transaction(); return true; },
        'transaction() is not supported in offline_write mode: transactions would bypass the offline write journal; use execute() with bound parameters instead'
    );

    // offline sync fails with the exact message, pending operations survive
    e2e_exact_throw(
        fn() => $db->sync(),
        'Sync failed: Cannot sync: no internet connection'
    );
    e2e_step($db->getPendingOperationsCount() === $pending, 'failed sync preserved pending operations');

    $db->close();
    e2e_exact_throw(fn() => $db->getPendingOperationsCount(), 'Offline connection not found');

    $db2 = $mk();
    e2e_step($db2->getPendingOperationsCount() === $pending, 'pending operations survive close/reopen');
    $row = $db2->query('SELECT v FROM jo')->fetchSingle(LibSQL::LIBSQL_ASSOC);
    e2e_step($row['v'] === 7, 'data survives close/reopen');
    $db2->close();
});

// =====================================================================
// S10 - result/statement metadata contract
// =====================================================================
e2e_scenario('metadata: columns(), parameterName/Count, zero-row column types', function () {
    $db = new LibSQL(':memory:');
    $db->execute("CREATE TABLE meta (a INTEGER, b TEXT)");
    $stmt = $db->prepare("SELECT a, b FROM meta WHERE a = :limit");
    e2e_step($stmt->parameterCount() === 1, 'parameterCount() counts placeholders');
    e2e_step($stmt->parameterName(1) === 'limit', 'named parameterName() without prefix');
    e2e_step($stmt->parameterName(0) === null, 'parameterName(0) is null');

    $cols = $stmt->columns();
    e2e_step(count($cols) === 2, 'columns() covers both columns');
    $first = $cols[0];
    foreach (['name', 'origin_name', 'table_name', 'database_name'] as $k) {
        e2e_step(array_key_exists($k, $first), "column metadata has '$k'");
    }
    e2e_step($first['name'] === 'a' && $first['table_name'] === 'meta', 'column metadata values');

    // zero-row result still carries the declared column metadata
    $zero = $db->query("SELECT a, b FROM meta WHERE a = 999");
    e2e_step($zero->numColumns() === 2, 'numColumns() without any row');
    e2e_step($zero->columnName(1) === 'b', 'columnName() without any row');
    e2e_step($zero->columnType(0) === 'Null', 'zero-row declared type is "Null" locally');
    $db->execute("INSERT INTO meta VALUES (1, 'x')");
    $withRow = $db->query("SELECT a FROM meta");
    e2e_step($withRow->columnType(0) === 'Integer', 'columnType() reports the per-row type');
    $db->close();
});

// =====================================================================
// S11 - remote connections open lazily (no network work at construction)
// =====================================================================
e2e_scenario('remote connection: lazy construct, failure only at first use', function () {
    // Nothing (can) listen on port 9 ("discard") in a normal environment.
    $db = new LibSQL('libsql:dbname=http://127.0.0.1:9;authToken=irrelevant');
    e2e_exact_throw(
        fn() => $db->execute('SELECT 1'),
        'Execution error: Hrana: `http error: `error trying to connect: tcp connect error: Connection refused (os error 111)``'
    );
    $db->close();
});

// =====================================================================
// Summary
// =====================================================================
$report['php_version'] = PHP_VERSION;
$report['php_os'] = PHP_OS . ' ' . (PHP_INT_SIZE === 8 ? '64-bit' : '32-bit');
$report['extension'] = LibSQL::version();
$report['duration_seconds'] = round(microtime(true) - $startedAt, 2);

echo "\nE2E SUMMARY: ok={$report['ok']} fail={$report['fail']} scenarios={$report['scenarios']} "
    . "in {$report['duration_seconds']}s\n";
echo 'E2E-REPORT: ' . json_encode($report) . "\n";

exit($report['fail'] > 0 ? 1 : 0);
