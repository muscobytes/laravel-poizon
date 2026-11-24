<?php

namespace Muscobytes\Poizon\Tests;

use Illuminate\Support\Facades\Http;
use Muscobytes\Poizon\PoizonServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PoizonServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.database', ['driver' => 'database', 'table' => 'cache', 'lock_table' => 'cache_locks', 'connection' => null, 'lock_connection' => null]);
        $app['config']->set('poizon.open_platform.wait_seconds', 0);
        $app['config']->set('poizon.open_platform.app_key', 'test-key');
        $app['config']->set('poizon.open_platform.app_secret', 'test-secret');
        $app['config']->set('poizon.open_platform.redirect_uri', 'https://another-service.example/oauth/callback');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }
}
