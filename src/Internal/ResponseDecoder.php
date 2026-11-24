<?php

namespace Muscobytes\Poizon\Internal;

use Illuminate\Http\Client\Response;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Enums\AuthorizationReason;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Exceptions\AuthorizationException;
use Muscobytes\Poizon\Exceptions\HttpException;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Exceptions\RateLimitException;

final class ResponseDecoder
{
    public function __construct(private Backoff $backoff) {}

    public function envelope(#[\SensitiveParameter] Response $response, ErrorContext $context): array
    {
        $status = $response->status();
        $body = null;
        try {
            $body = json_decode($response->body(), true, 512, JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
        }
        $code = is_array($body) ? ($body['code'] ?? null) : null;
        if (! is_int($code) || $code < -2147483648 || $code > 2147483647) {
            $code = null;
        }
        $error = new ErrorContext($context->operation, 'response', RequestState::ResponseReceived, $context->connection,
            $context->authorizationId, $status, $code, operationId: $context->operationId);
        if ($status === 429) {
            try {
                $retry = $this->backoff->remember($response->header('Retry-After'));
            } catch (PoizonException $exception) {
                throw $exception->withContext($error);
            }
            throw new RateLimitException(new ErrorContext($error->operation, 'response', RequestState::ResponseReceived,
                $error->connection, $error->authorizationId, $status, $code, $retry, $error->operationId));
        }
        if ($status === 401) {
            throw new AuthorizationException(AuthorizationReason::Rejected, $error);
        }
        if (! $response->successful()) {
            throw new HttpException($error);
        }
        if (! is_array($body) || $code === null || (isset($body['data']) && ! is_array($body['data']))) {
            throw new ProtocolException($error, field: $code === null && is_array($body) ? 'code' : 'data', actualType: $code === null && is_array($body) ? get_debug_type($body['code'] ?? null) : get_debug_type(is_array($body) ? ($body['data'] ?? null) : null));
        }
        if (array_key_exists('msg', $body) && $body['msg'] !== null && ! is_string($body['msg'])) {
            throw new ProtocolException($error, field: 'msg');
        }
        $body['code'] = $code;

        return $body;
    }
}
