<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();

        // Check the resolved connection before RefreshDatabase can run migrations.
        if (! $app->environment('testing')
            || $connection->getDriverName() !== 'pgsql'
            || $connection->getDatabaseName() !== 'lernumi_testing') {
            throw new RuntimeException('Feature tests require the PostgreSQL database lernumi_testing.');
        }

        return $app;
    }
}
