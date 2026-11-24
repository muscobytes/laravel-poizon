<?php

namespace Muscobytes\Poizon\Contracts;

interface AuthorizationRequiredNotifier
{
    public function notify(int $authorizationId, array $connections, string $reason): bool;
}
