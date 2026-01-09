<?php

namespace Naoray\LaravelGithubMonolog\Tests;

use Naoray\LaravelGithubMonolog\GithubMonologServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            GithubMonologServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Configure the default cache store to array for testing
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', [
            'driver' => 'array',
            'serialize' => false,
        ]);

        // Configure database for testing
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('logging.channels.github.tracing', [
            'enabled' => true,
            'requests' => true,
            'user' => true,
        ]);
    }
}
