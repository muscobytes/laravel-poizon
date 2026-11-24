<?php

namespace Muscobytes\Poizon;

use Illuminate\Support\ServiceProvider;
use Muscobytes\Poizon\Console\RefreshPoizonOAuthCommand;
use Muscobytes\Poizon\Contracts\AuthorizationRequiredNotifier;
use Muscobytes\Poizon\Internal\Diagnostics;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class PoizonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bindIf('poizon.logger', NullLogger::class);
        $this->app->when(Diagnostics::class)->needs(LoggerInterface::class)->give(fn () => $this->app->make('poizon.logger'));
        $this->mergeConfigFrom(__DIR__.'/../config/poizon.php', 'poizon');
        $this->app->bindIf(AuthorizationRequiredNotifier::class, fn () => new class implements AuthorizationRequiredNotifier
        {
            public function notify(int $authorizationId, array $connections, string $reason): bool
            {
                return false;
            }
        });
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->publishes([__DIR__.'/../config/poizon.php' => config_path('poizon.php')], 'poizon-config');
        if ($this->app->runningInConsole()) {
            $this->commands([RefreshPoizonOAuthCommand::class]);
        }
    }
}
