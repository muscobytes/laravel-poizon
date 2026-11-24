<?php

namespace Muscobytes\Poizon\Internal;

use Muscobytes\Poizon\Data\CallContext;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Exceptions\ApiException;
use Muscobytes\Poizon\Exceptions\ConfigurationException;
use Muscobytes\Poizon\Exceptions\CredentialStoreException;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\UnexpectedIntegrationException;
use Psr\Log\LoggerInterface;

final class Diagnostics
{
    public function __construct(private LoggerInterface $logger) {}

    public function completed(ErrorContext $context, float $started, int $attempts, ?PoizonException $failure = null, ?CallContext $call = null): void
    {
        $level = match (true) {
            $failure === null => 'debug',
            $failure instanceof ApiException => 'notice',
            $failure instanceof ConfigurationException,
            $failure instanceof CredentialStoreException,
            $failure instanceof UnexpectedIntegrationException => 'error',
            $context->operation === 'CreateOrder' => 'error',
            default => 'warning',
        };
        $this->write($level, 'poizon.operation.completed', $context->toArray() + ['attempts' => $attempts,
            'duration_ms' => round((hrtime(true) / 1e6) - $started, 3), 'error_type' => $failure === null ? null : class_basename($failure),
            'correlation_id' => $call?->correlationId, 'parent_operation_id' => $call?->parentOperationId]);
    }

    public function retry(ErrorContext $context, int $attempt, int $delay): void
    {
        $this->write('debug', 'poizon.retry.scheduled', $context->toArray() + ['attempt' => $attempt, 'delay_ms' => $delay]);
    }

    private function write(string $level, string $message, array $context): void
    {
        try {
            $this->logger->log($level, $message, array_filter($context, static fn ($v) => $v !== null));
        } catch (\Throwable) { /* Diagnostic delivery cannot change an operation. */
        }
    }
}
