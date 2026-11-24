<?php

namespace Muscobytes\Poizon\Console;

use Illuminate\Console\Command;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Services\AuthorizationManager;

class RefreshPoizonOAuthCommand extends Command
{
    protected $signature = 'poizon:oauth:refresh';

    protected $description = 'Refresh expiring Poizon authorizations';

    public function handle(AuthorizationManager $manager): int
    {
        try {
            $manager->refreshDue();
        } catch (PoizonException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
