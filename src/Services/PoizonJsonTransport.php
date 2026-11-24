<?php

namespace Muscobytes\Poizon\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Sleep;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Data\TransportResult;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\TransportException;
use Muscobytes\Poizon\Exceptions\UnexpectedIntegrationException;
use Muscobytes\Poizon\Internal\Diagnostics;
use Muscobytes\Poizon\Internal\RetryPolicy;

class PoizonJsonTransport
{
    public function __construct(private Factory $http, private Diagnostics $diagnostics) {}

    public function post(string $url, #[\SensitiveParameter] callable $payloadFactory, bool $retryRead = false, int $timeout = 15,
        #[\SensitiveParameter] array $headers = [], ErrorContext $context = new ErrorContext): TransportResult
    {
        $policy = RetryPolicy::configured();
        $attempts = $policy->attempts($retryRead);
        $timeout = min(15, max(1, $timeout));
        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $payload = $payloadFactory();
            } catch (\Throwable $exception) {
                $failureContext = new ErrorContext($context->operation, 'signing', $attempt === 1 ? RequestState::NotSent : RequestState::PossiblySent,
                    $context->connection, $context->authorizationId, operationId: $context->operationId, attempts: $attempt - 1);
                throw $exception instanceof PoizonException ? $exception->withContext($failureContext) : new UnexpectedIntegrationException($failureContext);
            }
            try {
                $request = $this->http->asJson()->acceptJson()->withHeaders($headers)->connectTimeout(min(10, $timeout))->timeout($timeout)->withoutRedirecting();
            } catch (\Throwable) {
                throw new UnexpectedIntegrationException(new ErrorContext($context->operation, 'http',
                    $attempt === 1 ? RequestState::NotSent : RequestState::PossiblySent, $context->connection,
                    $context->authorizationId, operationId: $context->operationId, attempts: $attempt - 1));
            }
            $httpContext = new ErrorContext($context->operation, 'http', RequestState::PossiblySent, $context->connection,
                $context->authorizationId, operationId: $context->operationId, attempts: $attempt);
            try {
                return new TransportResult($request->post($url, $payload), $attempt);
            } catch (ConnectionException) {
                if ($attempt === $attempts) {
                    throw new TransportException($httpContext);
                }
            } catch (\Throwable) {
                throw new UnexpectedIntegrationException($httpContext);
            }
            $delay = $policy->delay($attempt);
            $this->diagnostics->retry($httpContext, $attempt, $delay);
            Sleep::for($delay)->milliseconds();
        }
    }
}
