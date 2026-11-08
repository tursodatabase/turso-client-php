<?php

// Assert against Throwable, not Exception: argument-shape failures throw
// Error subclasses — passing only `config` and `offline_writes` triggers
// "Argument #2 ($sqld_offline_mode) must be passed explicitly", an
// ArgumentCountError that would pass a `not->toThrow(Exception::class)`
// check vacuously while the constructor never actually ran.

// Unlike plain remote DSNs, an embedded replica performs its first sync at
// construction time, so the remote must be reachable when the connection
// opens: an unreachable remote fails with
// "Replica database build failed: sync error: ...".
test('embedded replica connection fails when the remote is unreachable', function () {
    $file = sys_get_temp_dir() . '/turso-emb-unreach-' . uniqid() . '.db';

    try {
        expect(fn() => new LibSQL(
            config: [
                "url" => "file:" . $file,
                "authToken" => "irrelevant-until-first-use",
                "syncUrl" => "http://127.0.0.1:9", // dead port: nothing listens
                "syncInterval" => 5,
                "read_your_writes" => true,
                "encryptionKey" => "",
            ],
            sqld_offline_mode: false,
            flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
            encryption_key: "",
            offline_writes: false
        ))->toThrow(Exception::class);
    } finally {
        foreach (glob($file . '*') ?: [] as $leftover) {
            @unlink($leftover);
        }
    }
})->group('EmbeddedReplicaConnectionTest', 'DatabaseConnectionTest');

// Any HTTP server that answers is enough for the construction-time sync to
// connect — the response does not have to come from a real Hrana backend
// (404 counts); whatever is wrong with the remote then surfaces at first
// use, not at construction. PHP's built-in server plays the dummy remote
// and is stopped again afterwards.
test('embedded replica connection succeeds while the remote answers HTTP', function () {
    $port = 18453;
    $docroot = sys_get_temp_dir() . '/turso-emb-server-' . uniqid();
    $log = $docroot . '/server.log';
    mkdir($docroot);

    $proc = proc_open(
        PHP_BINARY . ' -S 127.0.0.1:' . $port,
        [1 => ['file', $log, 'w'], 2 => ['file', $log, 'w']],
        $pipes,
        $docroot
    );

    if (!is_resource($proc)) {
        rmdir($docroot);
        throw new Exception('could not start the built-in HTTP test server');
    }

    // Wait for the server to accept connections (up to two seconds).
    $up = false;
    for ($i = 0; $i < 40 && !$up; $i++) {
        usleep(50000);
        $probe = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $errstr, 0.25);
        $up = is_resource($probe);
        if ($up) {
            fclose($probe);
        }
    }

    $file = sys_get_temp_dir() . '/turso-emb-replica-' . uniqid() . '.db';

    try {
        if (!$up) {
            throw new Exception('built-in HTTP test server did not come up on port ' . $port);
        }

        $db = new LibSQL(
            config: [
                "url" => "file:" . $file,
                "authToken" => "irrelevant-until-first-use",
                "syncUrl" => "http://127.0.0.1:" . $port,
                "syncInterval" => 5,
                "read_your_writes" => true,
                "encryptionKey" => "",
            ],
            sqld_offline_mode: false,
            flags: LibSQL::OPEN_READWRITE | LibSQL::OPEN_CREATE,
            encryption_key: "",
            offline_writes: false
        );

        expect($db)->toBeInstanceOf(LibSQL::class);
        $db->close();
    } finally {
        foreach (glob($file . '*') ?: [] as $leftover) {
            @unlink($leftover);
        }
        proc_terminate($proc);
        proc_close($proc);
        @unlink($log);
        @rmdir($docroot);
    }
})->group('EmbeddedReplicaConnectionTest', 'DatabaseConnectionTest');
