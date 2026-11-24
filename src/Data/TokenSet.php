<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Exceptions\ProtocolException;

final readonly class TokenSet
{
    public function __construct(#[\SensitiveParameter] public string $openId, #[\SensitiveParameter] private string $accessToken, #[\SensitiveParameter] private string $refreshToken,
        public \DateTimeImmutable $accessExpiresAt, public \DateTimeImmutable $refreshExpiresAt, #[\SensitiveParameter] public string $scope)
    {
        foreach ([$openId, $accessToken, $refreshToken] as $i => $value) {
            if ($value === '' || strlen($value) > ($i === 0 ? 255 : 4096) || preg_match('/[\x00-\x20\x7f]/', $value)) {
                throw new ProtocolException;
            }
        }
        if ($scope === '' || strlen($scope) > 4096 || $accessExpiresAt->getTimestamp() <= now()->timestamp || $refreshExpiresAt->getTimestamp() <= now()->timestamp) {
            throw new ProtocolException;
        }
    }

    public function accessToken(): string
    {
        return $this->accessToken;
    }

    public function refreshToken(): string
    {
        return $this->refreshToken;
    }

    public function __debugInfo(): array
    {
        return ['credentials' => '[redacted]'];
    }

    public function __serialize(): array
    {
        throw new \LogicException('TokenSet serialization is prohibited.');
    }
}
