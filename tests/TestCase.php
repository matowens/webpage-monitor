<?php

namespace Rivetworks\WebpageMonitor\Tests;

use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;
use Rivetworks\WebpageMonitor\WebpageMonitorServiceProvider;

/**
 * Boots a minimal Laravel application for package-only Pest and PHPUnit coverage.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Register the package service provider through Testbench so package discovery
     * behavior is exercised without the Rivetworks host application.
     *
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [WebpageMonitorServiceProvider::class];
    }

    /**
     * Configure lightweight infrastructure defaults that match the package test expectations.
     */
    protected function defineEnvironment($app): void
    {
        /** @var Repository $config */
        $config = $app['config'];

        $config->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $config->set('database.default', 'sqlite');
        $config->set('database.connections.sqlite', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        $config->set('cache.default', 'array');
        $config->set('mail.default', 'array');
        $config->set('queue.default', 'sync');
        $config->set('queue.connections.database', [
            'driver' => 'database',
            'connection' => 'sqlite',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => false,
        ]);
        $config->set('queue.failed', [
            'driver' => 'database-uuids',
            'database' => 'sqlite',
            'table' => 'failed_jobs',
        ]);
    }

    /**
     * Load package and test-support migrations so standalone tests can exercise
     * persistence, queued jobs, and failed-job handling locally.
     */
    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }
}
