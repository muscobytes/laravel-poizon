<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Enums\AuthorizationReason;

final readonly class AuthorizationStatus
{
    public function __construct(public string $status, public ?string $openId = null, public ?\DateTimeImmutable $accessExpiresAt = null,
        public ?\DateTimeImmutable $refreshExpiresAt = null, public ?AuthorizationReason $reason = null, public ?string $notificationStatus = null) {}
}
