<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * A hard safety net, not just a documentation rule -- see
     * docs/developer/rca-2026-07-21-test-database-wipe.md. Every Feature
     * test uses RefreshDatabase, which runs migrate:fresh the moment
     * setUpTraits() executes -- which happens inside the base TestCase's
     * own setUp(), immediately after this method returns. Checking from
     * this project's own setUp() override would already be too late (this
     * was tried and proven too late by disabling the real fix and
     * confirming the dev database still got wiped even with the guard
     * present, before this method was moved here); createApplication() is
     * the last point that runs strictly before RefreshDatabase's hook.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = $app->make('config')->get('database.default');
        $database = $app->make('config')->get("database.connections.{$connection}.database");

        if ($connection !== 'sqlite' || $database !== ':memory:') {
            throw new RuntimeException(
                "Refusing to run tests: the resolved database connection is [{$connection}] against ".
                "database [{$database}], not the isolated sqlite :memory: connection phpunit.xml ".
                'declares. Running tests against any other database will drop every table in it '.
                '(RefreshDatabase runs migrate:fresh). See docs/developer/rca-2026-07-21-test-database-wipe.md.'
            );
        }

        return $app;
    }
}
