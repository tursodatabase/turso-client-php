<?php
/**
 * Production-flavor workload app for the libsql_php extension proof.
 *
 * A deliberately small router: each action opens its own connection in the
 * flavor that action requires, does real work, and closes it — exactly how a
 * per-request PHP app behaves under FPM.
 *
 *   local_*    durable file DB on a volume (restart-survival proof)
 *   remote_*   Hrana writes/reads against the live sqld service
 *   offline_*  journaled offline-write flavor; sync() replicates into the
 *              same live sqld
 *
 * Signatures follow libsql_php_extension.stubs.php: execute()/query() take the
 * parameters as their second argument.
 *
 * Loaded via /etc/php/8.5/fpm/conf.d/90-libsql.ini (packaged style).
 */

declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

header('Content-Type: application/json');

$storage = getenv('LIBSQL_DB_FILE') ?: '/var/www/storage/proof.db';
if (!is_dir(dirname($storage))) {
    @mkdir(dirname($storage), 0777, true);
}
$sqldAddr = getenv('SQLD_HTTP_ADDR') ?: 'http://sqld:8080';

$body = json_decode((string) file_get_contents('php://input') ?: '{}', true) ?: [];

function remote_dsn(string $addr): string
{
    return 'libsql:dbname=' . $addr . ';authToken=';
}

function offline_db(string $storage, string $addr): LibSQL
{
    return new LibSQL(
        config: [
            'url'     => 'file:' . $storage,
            // calibrated: an EMPTY authToken prevents the journaled flavor
            // from activating ("Pending operations only available in
            // offline_write mode"); a non-empty token is part of the flavor
            // contract
            'authToken' => 'tok',
            'syncUrl' => $addr,
            // calibrated: background auto-sync off; sync() is explicit
            'syncInterval' => 0,
            'read_your_writes' => true,
            'encryptionKey' => '',
        ],
        sqld_offline_mode: true,
        flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
        encryption_key: '',
        offline_writes: true
    );
}

function out(array $payload, int $code = 200): void
{
    http_response_code($code);
    echo json_encode(array_merge(['ok' => $code < 400], $payload)), "\n";
}

try {
    $action = $_GET['action'] ?? '';

    switch ($action) {
        // ------------------------------------------------------------ meta
        case 'health':
            out([
                'php'         => PHP_VERSION,
                'php_sapi'    => php_sapi_name(),
                'ext_version' => LibSQL::version(),
                'pid'         => (int) getmypid(),
                'sqld_addr'   => $sqldAddr,
            ]);
            break;

        // ----------------------------------------------- local file flavor
        case 'local_init':
            $db = new LibSQL('file:' . $storage);
            $db->execute('CREATE TABLE IF NOT EXISTS local_kv (k TEXT PRIMARY KEY, v TEXT)');
            $db->close();
            out(['file' => $storage, 'exists' => is_file($storage)]);
            break;

        case 'local_put':
            $k = (string) ($body['k'] ?? '');
            $v = (string) ($body['v'] ?? '');
            if ($k === '') {
                out(['ok' => false, 'why' => 'missing k'], 400);
                return;
            }
            $db = new LibSQL('file:' . $storage);
            $db->execute('REPLACE INTO local_kv (k, v) VALUES (?, ?)', [$k, $v]);
            $row = $db->query('SELECT COUNT(*) c FROM local_kv')->fetchSingle(LibSQL::LIBSQL_ASSOC);
            $db->close();
            out(['k' => $k, 'rows' => (int) $row['c']]);
            break;

        case 'local_get':
            $k = (string) ($_GET['k'] ?? $body['k'] ?? '');
            $db = new LibSQL('file:' . $storage);
            $row = $db->query('SELECT v FROM local_kv WHERE k = ?', [$k])->fetchSingle(LibSQL::LIBSQL_ASSOC);
            $db->close();
            out(['k' => $k, 'v' => $row['v'] ?? null]);
            break;

        // --------------------------------------------------- remote flavor
        case 'remote_init':
            $db = new LibSQL(remote_dsn($sqldAddr));
            $db->execute('CREATE TABLE IF NOT EXISTS kv (seq INTEGER PRIMARY KEY, k TEXT, v TEXT)');
            $db->close();
            out(['server' => $sqldAddr]);
            break;

        case 'remote_put':
            $k = (string) ($body['k'] ?? '');
            $v = (string) ($body['v'] ?? '');
            if ($k === '') {
                out(['ok' => false, 'why' => 'missing k'], 400);
                return;
            }
            $db = new LibSQL(remote_dsn($sqldAddr));
            $db->execute('INSERT INTO kv (k, v) VALUES (?, ?)', [$k, $v]);
            $row = $db->query('SELECT COUNT(*) c FROM kv')->fetchSingle(LibSQL::LIBSQL_ASSOC);
            $db->close();
            out(['k' => $k, 'rows' => (int) $row['c']]);
            break;

        case 'remote_get':
            $k = (string) ($_GET['k'] ?? '');
            $db = new LibSQL(remote_dsn($sqldAddr));
            $count = (int) ($db->query('SELECT COUNT(*) c FROM kv')->fetchSingle(LibSQL::LIBSQL_ASSOC)['c'] ?? 0);
            $last = null;
            if ($k !== '') {
                $row = $db->query('SELECT v FROM kv WHERE k = ? ORDER BY seq DESC LIMIT 1', [$k])->fetchSingle(LibSQL::LIBSQL_ASSOC);
                $last = $row['v'] ?? null;
            }
            $db->close();
            out(['count' => $count, 'last' => $last]);
            break;

        // --------------------------------------------- offline journal flow
        case 'offline_init':
            $db = offline_db($storage, $sqldAddr);
            $db->execute('CREATE TABLE IF NOT EXISTS jour (k TEXT PRIMARY KEY, v TEXT)');
            $pending = $db->getPendingOperationsCount();
            $db->close();
            out(['pending_after_init' => $pending, 'file' => $storage]);
            break;

        case 'offline_put':
            $k = (string) ($body['k'] ?? '');
            $v = (string) ($body['v'] ?? '');
            if ($k === '') {
                out(['ok' => false, 'why' => 'missing k'], 400);
                return;
            }
            $db = offline_db($storage, $sqldAddr);
            $db->execute('REPLACE INTO jour (k, v) VALUES (?, ?)', [$k, $v]);
            $row = $db->query('SELECT COUNT(*) c FROM jour')->fetchSingle(LibSQL::LIBSQL_ASSOC);
            $pending = $db->getPendingOperationsCount();
            $db->close();
            out(['k' => $k, 'jour_rows_local' => (int) $row['c'], 'pending' => $pending]);
            break;

        case 'offline_status':
            $db = offline_db($storage, $sqldAddr);
            $jourRows = null;
            try {
                $jourRows = (int) ($db->query('SELECT COUNT(*) c FROM jour')->fetchSingle(LibSQL::LIBSQL_ASSOC)['c'] ?? 0);
            } catch (Throwable $e) {
                // jour only exists once offline_init ran; the readiness probe
                // legitimately runs first and must not 500 on its call
            }
            out([
                'pending'        => $db->getPendingOperationsCount(),
                'is_online'      => $db->isOnline(),
                'jour_rows_local' => $jourRows,
            ]);
            $db->close();
            break;

        case 'offline_sync':
            $db = offline_db($storage, $sqldAddr);
            $db->sync();
            $pending = $db->getPendingOperationsCount();
            $db->close();
            out(['pending_after_sync' => $pending]);
            break;

        // --------------------------------------------- process observations
        case 'rss':
            $rss = 0;
            foreach (file('/proc/self/status') as $line) {
                if (str_starts_with($line, 'VmRSS:')) {
                    $rss = (int) (explode("\t", trim($line))[1]); // kB
                }
            }
            out(['vm_rss_kb' => $rss, 'pid' => (int) getmypid()]);
            break;

        // Leak guard needs ONE process across all churning: fpm's pool gives
        // each request an arbitrary worker, so per-request RSS readings are
        // not comparable (sampled empirically: delta even went negative).
        // This endpoint churns open→write→read→close cycles inside a single
        // process — the same lifecycle a busy worker pool repeats — and
        // returns the within-process RSS delta.
        case 'churn':
            $n = max(1, min(1000, (int) ($_GET['n'] ?? $body['n'] ?? 60)));
            $rss0 = 0;
            foreach (file('/proc/self/status') as $line) {
                if (str_starts_with($line, 'VmRSS:')) {
                    $rss0 = (int) (explode("\t", trim($line))[1]);
                }
            }
            for ($i = 0; $i < $n; $i++) {
                $db = new LibSQL('file:' . $storage);
                $db->execute('REPLACE INTO local_kv (k, v) VALUES (?, ?)', ["churn:$i", 'x']);
                $db->query('SELECT COUNT(*) c FROM local_kv')->fetchSingle(LibSQL::LIBSQL_ASSOC);
                $db->close();
            }
            $rss1 = 0;
            foreach (file('/proc/self/status') as $line) {
                if (str_starts_with($line, 'VmRSS:')) {
                    $rss1 = (int) (explode("\t", trim($line))[1]);
                }
            }
            out(['cycles' => $n, 'rss_before_kb' => $rss0, 'rss_after_kb' => $rss1, 'delta_kb' => $rss1 - $rss0]);
            break;

        default:
            out(['ok' => false, 'why' => 'unknown action', 'action' => $action], 404);
    }
} catch (Throwable $e) {
    out(['ok' => false, 'why' => get_class($e) . ': ' . $e->getMessage()], 500);
}
