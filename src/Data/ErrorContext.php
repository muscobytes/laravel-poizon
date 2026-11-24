<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Enums\Operation;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Support\ConnectionName;

final readonly class ErrorContext
{
    public function __construct(
        public string $operation = 'unknown',
        public string $stage = 'preparation',
        public RequestState $requestState = RequestState::NotSent,
        public ?string $connection = null,
        public ?int $authorizationId = null,
        public ?int $httpStatus = null,
        public ?int $upstreamCode = null,
        public ?int $retryAfter = null,
        public ?string $operationId = null,
        public int $attempts = 0,
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'operation' => in_array($this->operation, array_column(Operation::cases(), 'name'), true) ? $this->operation : 'unknown',
            'stage' => in_array($this->stage, ['preparation', 'credentials', 'signing', 'http', 'response', 'storage', 'mutex'], true) ? $this->stage : 'unknown',
            'request_state' => $this->requestState->value,
            'connection' => ConnectionName::valid($this->connection) ? $this->connection : null,
            'authorization_id' => $this->authorizationId,
            'http_status' => $this->httpStatus,
            'upstream_code' => $this->upstreamCode,
            'retry_after' => $this->retryAfter,
            'operation_id' => $this->operationId,
        ], static fn ($value) => $value !== null);
    }
}
