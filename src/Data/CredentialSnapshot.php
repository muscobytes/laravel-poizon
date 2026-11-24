<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Enums\CredentialFailure;
use Muscobytes\Poizon\Exceptions\CredentialStoreException;

final readonly class CredentialSnapshot
{
    public function __construct(public int $authorizationId, #[\SensitiveParameter] private string $token)
    {
        if ($token === '' || strlen($token) > 4096 || preg_match('/[\x00-\x20\x7f]/', $token)) {
            throw new CredentialStoreException(CredentialFailure::Encryption);
        }
    }

    public function accessToken(): string
    {
        return $this->token;
    }

    public function __debugInfo(): array
    {
        return ['authorization_id' => $this->authorizationId];
    }

    public function __serialize(): array
    {
        throw new \LogicException('Credential serialization is prohibited.');
    }
}
