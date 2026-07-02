<?php

use Tests\TestCase;

uses(TestCase::class);

describe('Metadata Compatibility', function () {
    test('numeric fetch preserves duplicate column ordering', function () {
        $row = $this->db
            ->query('SELECT 10 AS name, 20 AS name, 30 AS type, 40 AS type, 50 AS notnull, 60 AS dflt_value, 70 AS pk')
            ->fetchSingle(LibSQL::LIBSQL_NUM);

        expect($row)->toBe([10, 20, 30, 40, 50, 60, 70]);
    });

    test('numeric fetch array preserves duplicate column ordering', function () {
        $rows = $this->db
            ->query('SELECT 1 AS name, 2 AS name, 3 AS type, 4 AS type UNION ALL SELECT 5, 6, 7, 8')
            ->fetchArray(LibSQL::LIBSQL_NUM);

        expect($rows)->toBe([
            [1, 2, 3, 4],
            [5, 6, 7, 8],
        ]);
    });
})->group('MetadataCompatibilityTest', 'Feature');
