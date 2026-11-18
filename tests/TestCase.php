<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestSchema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Safety net: the .env of this project points at the real database. Never touch it from tests.
        if (DB::connection()->getDriverName() !== 'sqlite') {
            throw new \RuntimeException('Tests must run on in-memory SQLite, never on a real database.');
        }

        TestSchema::create();
        Artisan::call('migrate', ['--force' => true]);
    }
}
