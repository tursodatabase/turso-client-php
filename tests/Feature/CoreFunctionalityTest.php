<?php

use Tests\TestCase;

uses(TestCase::class);

describe('LibSQL Core Functionality', function () {
    it('database version information', function () {
        $version = $this->db::version();
        expect($version)
            ->toBeString()
            ->toContain('LibSQL Core Version')
            ->toContain('LibSQL PHP Extension Version');
    });

    it('connection status and autocommit mode', function () {
        expect($this->db->isAutocommit())->toBeTrue();
    });
})->group('CoreFunctionalityTest', 'Feature');
