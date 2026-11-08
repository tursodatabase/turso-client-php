<?php
/**
 * Workload runner for the production-topology proof (docker compose).
 *
 * Phases:
 *   phase=main           readiness + health + remote-tier workload (through
 *                        the web tier) + cross-client check + leak guard
 *   phase=offline-write  while the orchestrator has cut app's access to the
 *                        server: journaled writes accrue, server has ZERO of
 *                        them
 *   phase=offline-sync   after the orchestrator reconnected app: sync(),
 *                        then the second client reads the jour rows BACK
 *                        through sqld, key+value exact
 *   phase=persist        after the orchestrator restarted the APP container:
 *                        local file DB data must survive, server unaffected
 *   phase=persist-server after restarting the SQLD container: its volume data
 *                        must survive; the app must talk to it again
 *   phase=report         merge phase reports into the final PROOF JSON
 *
 * The runner NEVER talks to the docker daemon: it speaks HTTP (through nginx,
 * like an external client) and Hrana (directly to sqld, as a second,
 * independent observer). Restarts happen between phases via proof.sh, so each
 * assert runs inside a realistic production state.
 *
 * Output: per-step lines, `PHASE-REPORT: {...}` per phase, and a
 * final single-line `PROOF: {...}`, plus /proof-report/full-report.json.
 *
 * Exit code is non-zero when any assert fails — CI gates on it.
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', 'stderr');

$web = rtrim(getenv('WEB_BASE_URL') ?: 'http://web', '/');
$sqldAddr = getenv('SQLD_HTTP_ADDR') ?: 'http://sqld:8080';
$phase = $_SERVER['argv'][1] ?? 'main';
$reportDir = getenv('PROOF_REPORT_DIR') ?: '/proof-report';
if (!is_dir($reportDir)) {
    mkdir($reportDir, 0777, true);
}

$okSteps = [];
$failSteps = [];
$notes = [];

/**
 * Records one step. $ok=false marks the phase as failed (exit non-zero).
 *
 * @param array<string,mixed> $detail scalar-only detail dict (json-able)
 */
function step(string $name, bool $ok, array $detail = []): void
{
    global $okSteps, $failSteps;
    if ($ok) {
        $okSteps[] = ['name' => $name, 'detail' => $detail];
        echo '  OK    ' . $name . ($detail ? ' — ' . json_encode($detail, JSON_UNESCAPED_SLASHES) : '') . "\n";
    } else {
        $failSteps[] = ['name' => $name, 'detail' => $detail];
        echo '  FAIL  ' . $name . ($detail ? ' — ' . json_encode($detail, JSON_UNESCAPED_SLASHES) : '') . "\n";
    }
}

/**
 * One HTTP round through nginx (the public tier). JSON body in, JSON out;
 * non-JSON or HTTP≥400 is reported as an unusable response. $timeout is
 * per-request: the churn guard runs minutes-long fpm requests internally
 * (observed ~165 ms per open→write→read→close cycle), so the default 20 s
 * cap must not apply there.
 *
 * @param array<string,string> $params
 * @return array<string,mixed> response body with `__http` added
 */
function call(string $action, array $params = [], string $method = 'GET', int $timeout = 20): array
{
    global $web;
    static $ch = null;
    if ($ch === null) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
        ]);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    $url = $web . '/?action=' . urlencode($action);
    if ($method === 'GET') {
        $url .= '&' . http_build_query($params);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
    } else {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params, JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $body = json_decode((string) $raw, true);
    if (!is_array($body)) {
        $body = ['ok' => false, 'why' => 'non-json (http ' . $code . ')', 'raw' => substr((string) $raw, 0, 200)];
    }
    $body['__http'] = $code;
    if ($code >= 400) {
        $body['ok'] = false;
    }
    return $body;
}

/**
 * A second, independent Hrana client hitting sqld directly (bypassing the
 * app tier). Returns count, or the full jour table when $want_rows.
 * A missing table on a fresh volume IS zero rows (nothing exists yet) —
 * reported as ok with `absent: true` instead of a failure.
 *
 * @return array{ok:bool,count?:int,rows?:array<int,array<string,mixed>>,absent?:bool,why?:string}
 */
function direct_client(string $table, bool $want_rows = false): array
{
    global $sqldAddr;
    try {
        $db = new LibSQL('libsql:dbname=' . $sqldAddr . ';authToken=');
        $row = $db->query('SELECT COUNT(*) c FROM ' . $table)->fetchSingle(LibSQL::LIBSQL_ASSOC);
        $count = (int) ($row['c'] ?? 0);
        $rows = [];
        if ($want_rows) {
            $all = $db->query('SELECT k, v FROM ' . $table . ' ORDER BY k')->fetchArray(LibSQL::LIBSQL_ASSOC);
            $rows = $all;
        }
        $db->close();
        return array_merge(['ok' => true, 'count' => $count], $want_rows ? ['rows' => $rows] : []);
    } catch (Throwable $e) {
        if (str_contains(strtolower($e->getMessage()), 'no such table')) {
            // fresh server volume: the table appears only when the first
            // replica syncs/drains — absent table == zero rows existed
            $ret = ['ok' => true, 'count' => 0, 'absent' => true];
            return $want_rows ? array_merge($ret, ['rows' => []]) : $ret;
        }
        return ['ok' => false, 'why' => get_class($e) . ': ' . $e->getMessage()];
    }
}

if (!class_exists('LibSQL')) {
    fwrite(STDERR, "extension not loaded in the runner (cli conf.d ini missing)\n");
    exit(2);
}

/**
 * Polls a probe until it passes (readiness of tiers whose containers we do
 * not manage here — the official sqld image has no per-service healthcheck:
 * it ships no network tools and its shell is dash, so /dev/tcp tricks do not
 * work there; the runner's own Hrana client is the honest sensor).
 *
 * @return bool true when ready before the deadline
 */
function wait_ready(string $what, callable $probe, int $maxSeconds = 30): bool
{
    $deadline = microtime(true) + $maxSeconds;
    $tries = 0;
    while (microtime(true) < $deadline) {
        $tries++;
        try {
            if ($probe()) {
                step("ready: $what", true, ['tries' => $tries]);
                return true;
            }
        } catch (Throwable $e) {
            // keep polling; the probe's failure loop is exactly the point
        }
        usleep(500000);
    }
    step("ready: $what", false, ['tries' => $tries, 'exhausted_seconds' => $maxSeconds]);
    return false;
}

/** True once the web→fpm tier answers a health request. */
function web_probe(): bool
{
    $h = call('health');
    return (bool) ($h['ok'] ?? false)
        && str_contains((string) ($h['ext_version'] ?? ''), 'LibSQL PHP Extension Version: ');
}

/**
 * True once the server answers a direct Hrana query — the true "sqld is up"
 * signal, independent from the app tier.
 */
function sqld_probe(): bool
{
    global $sqldAddr;
    try {
        $db = new LibSQL('libsql:dbname=' . $sqldAddr . ';authToken=');
        $row = $db->query('SELECT 1 AS ping')->fetchSingle(LibSQL::LIBSQL_ASSOC);
        $db->close();
        return (int) ($row['ping'] ?? 0) === 1;
    } catch (Throwable $e) {
        return false;
    }
}

echo "PROOF phase: $phase (web=$web sqld=$sqldAddr)\n";

if ($phase === 'main') {
    // ------------------------------------------------------- 0. readiness
    $webReady = wait_ready('nginx→fpm→extension tier', 'web_probe');
    $sqldReady = wait_ready('sqld speaks Hrana', 'sqld_probe');
    if (!$webReady || !$sqldReady) {
        fwrite(STDERR, "tiers never became ready; aborting phase before asserts\n");
        exit(1);
    }

    // ---------------------------------------------------------- 1. health
    $h = call('health');
    step(
        'health: nginx→fpm→extension loaded via packaged ini',
        ($h['ok'] ?? false)
            && str_contains((string) ($h['ext_version'] ?? ''), 'LibSQL PHP Extension Version: ')
            // observed: sury fpm reports "fpm-fcgi" (not "fpm-fpm")
            && str_starts_with((string) ($h['php_sapi'] ?? ''), 'fpm'),
        ['php' => $h['php'] ?? '?', 'sapi' => $h['php_sapi'] ?? '?', 'ext' => $h['ext_version'] ?? '?']
    );
    $notes['app_meta'] = [$h['php'] ?? '?', $h['php_sapi'] ?? '?', $h['ext_version'] ?? '?'];

    // ----------------------------------------------------------- 2. setup
    $r1 = call('remote_init');
    $l1 = call('local_init');
    step(
        'setup: remote + local-file flavors initialize',
        ($r1['ok'] ?? false) && ($l1['ok'] ?? false),
        []
    );
    // NOTE: the offline journal is initialized in phase offline-write, while
    // the app is disconnected from sqld (calibration insight: with a
    // REACHABLE server the flavor auto-syncs eagerly — pending would live
    // exactly one statement. The journaled story needs the outage window.)

    // ---------------------------------------- 3. remote writes (app tier)
    $N = 50;
    $allOk = true;
    for ($i = 1; $i <= $N; $i++) {
        $p = call('remote_put', ['k' => 'proof:kv-' . $i, 'v' => 'value-' . $i], 'POST');
        if (($p['ok'] ?? false) && (int) ($p['rows'] ?? 0) === $i) {
            continue;
        }
        $allOk = false;
        step("remote write $i", false, ['resp' => $p]);
        break;
    }
    step("remote tier: $N Hrana writes through nginx→fpm; row count grows 1..$N", $allOk, ['writes' => $N]);

    // --------------------------------------------- 4. read back via app
    $g = call('remote_get', ['k' => 'proof:kv-' . $N]);
    step(
        'remote tier: read-back via app returns the exact last row/value',
        ($g['ok'] ?? false) && (int) ($g['count'] ?? 0) === $N && ($g['last'] ?? '') === 'value-' . $N,
        ['count' => $g['count'] ?? '?', 'last' => $g['last'] ?? '?']
    );

    // ------------------------- 5. cross-client: runner reads the server itself
    $d = direct_client('kv');
    step(
        'cross-client: the runner\'s own Hrana client sees all rows via sqld',
        ($d['ok'] ?? false) && (int) ($d['count'] ?? 0) === $N,
        ['count' => $d['count'] ?? '?']
    );

    // --------------------------------- 6. seed + 7. leak guard (pre-restart)
    $seed = call('local_put', ['k' => 'proof:seed', 'v' => '1'], 'POST');
    step(
        'seed: durable local value written for the restart phase',
        ($seed['ok'] ?? false) && (int) ($seed['rows'] ?? 0) === 1,
        ['k' => 'proof:seed', 'v' => '1']
    );

    // Leak guard, honestly instrumented: fpm hands each HTTP request an
    // arbitrary pool worker, so cross-request /proc RSS readings are NOT
    // comparable (observed: the naive sampler even reported a negative
    // delta). The churn action instead runs N open→write→read→close
    // connection cycles INSIDE one fpm process and returns that process's
    // RSS delta around them — the exact lifecycle a busy pool repeats.
    // Warm-up absorbs one-time costs; the measured window must stay flat.
    // Calibrated: a cold pool worker pays ≤ ~5.8 MB of first-touch cost
    // (still inside the bound); the old fail-mode (~140 kB retained per
    // connection cycle) would blow straight past 8192 kB at 200 cycles.
    $warm = call('churn', ['n' => 200], 'GET', 120);
    $measured = call('churn', ['n' => 200], 'GET', 120);
    $warmDelta = (int) ($warm['delta_kb'] ?? -99999);
    $measuredDelta = (int) ($measured['delta_kb'] ?? -99999);
    $leakOk = ($warm['ok'] ?? false) && ($measured['ok'] ?? false)
        && $measuredDelta >= 0 && $measuredDelta <= 8192;
    step(
        'leak guard: in-process RSS delta across 200 open→write→read→close cycles bounded',
        $leakOk,
        ['warm_delta_kb' => $warmDelta, 'measured_delta_kb' => $measuredDelta, 'threshold_kb' => 8192]
    );
    $notes['churn'] = ['warm_delta_kb' => $warmDelta, 'measured_delta_kb' => $measuredDelta];
}

if ($phase === 'offline-write') {
    // The orchestrator has cut the app's access to server-net: the extension
    // now behaves like production during an outage.
    $offReady = wait_ready('app flavor sees the outage (isOnline=false)', static function (): bool {
        $s = call('offline_status');
        return ($s['ok'] ?? false) && ($s['is_online'] ?? null) === false;
    });
    if (!$offReady) {
        fwrite(STDERR, "app never presented as offline; aborting\n");
        exit(1);
    }

    // ------------------------------------------------- 1. offline init
    $o = call('offline_init');
    $afterInit = (int) ($o['pending_after_init'] ?? -1);
    step(
        'offline init: journaled flavor constructs OFFLINE (CREATE journaled)',
        ($o['ok'] ?? false) && $afterInit === 1,
        ['pending_after_init' => $afterInit]
    );
    $notes['pending_after_init'] = $afterInit;

    // ------------------------- 2. journaled writes, pending grows per statement
    $M = 3;
    $offlineOk = true;
    for ($i = 1; $i <= $M; $i++) {
        $p = call('offline_put', ['k' => 'proof:offline-' . $i, 'v' => 'offline-value-' . $i], 'POST');
        $wantPending = $afterInit + $i;
        if (!(($p['ok'] ?? false) && (int) ($p['pending'] ?? 0) === $wantPending
              && (int) ($p['jour_rows_local'] ?? 0) === $i)) {
            $offlineOk = false;
            step("offline write $i", false, ['resp' => $p, 'expected_pending' => $wantPending]);
            break;
        }
    }
    step(
        "offline journal: $M journaled writes; pending grows $afterInit→" . ($afterInit + $M),
        $offlineOk,
        ['writes' => $M]
    );
    $notes['pending_before_sync'] = $afterInit + $M;

    // ------------------- 3. server-blind: the journal rows are NOT on sqld yet
    $before = direct_client('jour');
    step(
        'server-blind: sqld has ZERO jour rows during the outage (journal-only writes)',
        ($before['ok'] ?? false) && (int) ($before['count'] ?? -1) === 0,
        ['server_count' => $before['count'] ?? '?', 'absent' => $before['absent'] ?? false, 'why' => $before['why'] ?? '']
    );

    // ------------- 4. the outage is surgical: the server itself stands (kv intact)
    $kv = direct_client('kv');
    step(
        'server unaffected by the outage (kv rows still 50)',
        ($kv['ok'] ?? false) && (int) ($kv['count'] ?? 0) === 50,
        ['count' => $kv['count'] ?? '?']
    );
}

if ($phase === 'offline-sync') {
    // The orchestrator has reconnected the app to server-net. Whether the
    // backlog drained on the flavor's eager auto-sync-at-open or via the
    // explicit sync() below, the asserts are about the SERVER STATE.
    $onReady = wait_ready('app flavor back online (isOnline=true)', static function (): bool {
        $s = call('offline_status');
        return ($s['ok'] ?? false) && ($s['is_online'] ?? null) === true;
    });
    if (!$onReady) {
        fwrite(STDERR, "app never returned to online state; aborting\n");
        exit(1);
    }

    // ------------------------------------------------------- 1. sync
    $s = call('offline_sync');
    step(
        'sync(): journal content is replicated; pending == 0 after',
        ($s['ok'] ?? false) && (int) ($s['pending_after_sync'] ?? -1) === 0,
        ['pending_after_sync' => $s['pending_after_sync'] ?? '?']
    );

    // ------------------------------- 2. cross-client E2E: exact rows on the server
    $after = direct_client('jour', true);
    $rowsOk = ($after['ok'] ?? false) && (int) ($after['count'] ?? -1) === 3;
    if ($rowsOk) {
        foreach (($after['rows'] ?? []) as $i => $row) {
            $want = $i + 1;
            if (($row['k'] ?? '') !== 'proof:offline-' . $want || ($row['v'] ?? '') !== 'offline-value-' . $want) {
                $rowsOk = false;
                break;
            }
        }
    }
    step(
        'cross-client E2E: journaled rows are live on the server, key+value exact, nothing duplicated',
        $rowsOk,
        ['server_rows' => count($after['rows'] ?? [])]
    );
}

if ($phase === 'persist') {
    $ready = wait_ready('app tier restarted and answering', 'web_probe');
    if (!$ready) {
        fwrite(STDERR, "app tier never became ready after restart; aborting\n");
        exit(1);
    }
    $g = call('local_get', ['k' => 'proof:seed']);
    step(
        'durability: app container restarted; local file DB row survived',
        ($g['ok'] ?? false) && ($g['v'] ?? null) === '1',
        ['k' => 'proof:seed', 'v' => $g['v'] ?? '?']
    );
    $h = call('health');
    step(
        'fresh fpm worker passes health (packaged ini)',
        ($h['ok'] ?? false) && str_contains((string) ($h['ext_version'] ?? ''), 'LibSQL PHP Extension Version: '),
        ['pid' => $h['pid'] ?? '?']
    );
    $sqlds = direct_client('kv');
    step(
        'server unaffected by the app restart (still 50 kv rows)',
        ($sqlds['ok'] ?? false) && (int) ($sqlds['count'] ?? 0) === 50,
        ['count' => $sqlds['count'] ?? '?']
    );
}

if ($phase === 'persist-server') {
    $ready = wait_ready('sqld restarted and speaks Hrana again', 'sqld_probe');
    if (!$ready) {
        fwrite(STDERR, "sqld never came back after restart; aborting\n");
        exit(1);
    }
    $sqlds = direct_client('kv');
    step(
        'server durability: sqld container restarted; its volume data survived',
        ($sqlds['ok'] ?? false) && (int) ($sqlds['count'] ?? 0) === 50,
        ['count' => $sqlds['count'] ?? '?']
    );
    $g = call('remote_get', ['k' => 'proof:kv-1']);
    step(
        'app tier reaches the restarted server (last == value-1)',
        ($g['ok'] ?? false) && ($g['last'] ?? '') === 'value-1',
        ['last' => $g['last'] ?? '?']
    );
}

if ($phase === 'report') {
    $files = glob($reportDir . '/report-*.json') ?: [];
    sort($files);
    $merged = [
        'run_id' => uniqid('proof-'),
        'phases' => [],
        'ok' => 0,
        'fail' => 0,
        'all_ok' => true,
        'meta' => ['php' => PHP_VERSION, 'ext' => LibSQL::version()],
    ];
    foreach ($files as $file) {
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            continue;
        }
        $merged['phases'][$data['phase'] ?? basename($file)] = $data;
        $merged['ok'] += count($data['okSteps'] ?? []);
        $merged['fail'] += count($data['failSteps'] ?? []);
        if (count($data['failSteps'] ?? []) > 0) {
            $merged['all_ok'] = false;
        }
    }
    $merged['notes'] = ['collected_from' => array_map(static fn ($f) => basename($f), $files)];
    file_put_contents($reportDir . '/full-report.json', json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    echo "\nPROOF: " . json_encode([
        'ok'         => $merged['ok'],
        'fail'       => $merged['fail'],
        'all_ok'     => $merged['all_ok'],
        'meta'       => $merged['meta'],
        'phases'     => array_map(static fn ($p) => ['ok' => count($p['okSteps']), 'fail' => count($p['failSteps'])], $merged['phases']),
    ], JSON_UNESCAPED_SLASHES) . "\n";
    exit($merged['all_ok'] ? 0 : 1);
}

// ------------------------------------------------------ write phase report
$phaseReport = [
    'phase'    => $phase,
    'okSteps'  => $okSteps,
    'failSteps' => $failSteps,
    'notes'    => $notes,
];
file_put_contents(
    $reportDir . '/report-' . $phase . '.json',
    json_encode($phaseReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n"
);
echo 'PHASE-REPORT: ' . json_encode([
    'phase' => $phase,
    'ok'    => count($okSteps),
    'fail'  => count($failSteps),
]) . "\n";

exit(count($failSteps) > 0 ? 1 : 0);
