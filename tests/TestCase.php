<?php

namespace Tests;

use LibSQL;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public LibSQL $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = new LibSQL(':memory:');
    }

    protected function tearDown(): void
    {
        $this->db->close();
        parent::tearDown();
    }
}