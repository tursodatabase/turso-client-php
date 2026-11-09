<?php

use Tests\TestCase;

uses(TestCase::class)->group('TypedBindings', 'Feature');

test('prepared statement bindings keep native storage classes', function () {
    $this->db->execute("CREATE TABLE typed (name TEXT, age INTEGER, score REAL, blob BLOB)");

    $stmt = $this->db->prepare("INSERT INTO typed (name, age, score, blob) VALUES (?, ?, ?, ?)");
    $stmt->bindPositional(['Ada', 42, 1.5, [1, 2, 255]]);
    expect($stmt->execute())->toBe(1);
    $stmt->finalize();

    $row = $this->db->query(
        "SELECT typeof(name) AS tn, typeof(age) AS ta, typeof(score) AS ts, typeof(blob) AS tb FROM typed"
    )->fetchSingle(LibSQL::LIBSQL_ASSOC);

    expect($row)
        ->tn->toBe('text')
        ->ta->toBe('integer')
        ->ts->toBe('real')
        ->tb->toBe('blob');
});

test('named bindings keep native storage classes', function () {
    $this->db->execute("CREATE TABLE typed_named (age INTEGER, blob BLOB)");

    $stmt = $this->db->prepare("INSERT INTO typed_named (age, blob) VALUES (:age, :b)");
    $stmt->bindNamed([':age' => 33, ':b' => [9, 128, 0]]);
    expect($stmt->execute())->toBe(1);
    $stmt->finalize();

    $row = $this->db->query(
        "SELECT typeof(age) AS ta, typeof(blob) AS tb, hex(blob) AS h FROM typed_named"
    )->fetchSingle(LibSQL::LIBSQL_ASSOC);

    expect($row)
        ->ta->toBe('integer')
        ->tb->toBe('blob')
        ->h->toBe('098000');
});

test('boolean parameters bind as 1/0', function () {
    $this->db->execute("CREATE TABLE bools (v)");

    $this->db->execute("INSERT INTO bools (v) VALUES (?)", [true]);
    $this->db->execute("INSERT INTO bools (v) VALUES (?)", [false]);

    $rows = $this->db->query("SELECT v FROM bools")->fetchArray(LibSQL::LIBSQL_NUM);
    expect((int) $rows[0][0])->toBe(1)
        ->and((int) $rows[1][0])->toBe(0);
});

test('positional statement bindings are applied in placeholder order', function () {
    $this->db->execute("CREATE TABLE ordered (a INTEGER, b INTEGER, c INTEGER)");

    $stmt = $this->db->prepare("INSERT INTO ordered (a, b, c) VALUES (?1, ?2, ?3)");
    $stmt->bindPositional([111, 222, 333]);
    expect($stmt->execute())->toBe(1);
    $stmt->finalize();

    $row = $this->db->query("SELECT a, b, c FROM ordered")->fetchSingle(LibSQL::LIBSQL_ASSOC);
    expect((int) $row['a'])->toBe(111)
        ->and((int) $row['b'])->toBe(222)
        ->and((int) $row['c'])->toBe(333);
});

test('LAZY fetch streams rows lazily', function () {
    $this->db->execute("CREATE TABLE lazy_rows (name TEXT, age INTEGER)");

    $stmt = $this->db->prepare("INSERT INTO lazy_rows (name, age) VALUES (?, ?)");
    for ($i = 1; $i <= 60; $i++) {
        $stmt->bindPositional(["user$i", $i]);
        $stmt->execute();
    }
    $stmt->finalize();

    $iterator = $this->db->query("SELECT * FROM lazy_rows")->fetchArray(LibSQL::LIBSQL_LAZY);

    expect($iterator)->toBeInstanceOf(LibSQLIterator::class);

    // The documented do/while pattern iterates the lazily streamed rows.
    $count = 0;
    $first = null;
    $last = null;
    do {
        $row = $iterator->current();
        $count++;
        if ($row) {
            if ($first === null) {
                $first = $row;
            }
            $last = $row;
        }
        $iterator->next();
    } while ($iterator->valid());

    expect($count)->toBe(60)
        ->and($first['name'])->toBe('user1')
        ->and((int) $first['age'])->toBe(1)
        ->and($last['name'])->toBe('user60')
        // rows carry both string and numeric keys (fetchArray BOTH shape)
        ->and($last[0])->toBe('user60')
        ->and($iterator->key())->toBe(60);
});

test('numColumns works on zero-row queries', function () {
    $this->db->execute("CREATE TABLE empty_meta (alpha, beta TEXT, gamma INTEGER)");

    $result = $this->db->query("SELECT * FROM empty_meta WHERE 1 = 0");

    expect($result->numColumns())->toBe(3)
        ->and($result->columnName(1))->toBe('beta');
});
