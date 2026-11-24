<?php

namespace Muscobytes\Poizon\Services;

use Illuminate\Support\Str;
use Muscobytes\Poizon\Data\ApiErrorResponse;
use Muscobytes\Poizon\Data\CallContext;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Data\ResponseMetadata;
use Muscobytes\Poizon\Data\TokenSet;
use Muscobytes\Poizon\Enums\Operation;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Events\OAuthFailed;
use Muscobytes\Poizon\Exceptions\ApiException;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Internal\Backoff;
use Muscobytes\Poizon\Internal\Diagnostics;
use Muscobytes\Poizon\Internal\ResponseDecoder;
use Muscobytes\Poizon\Internal\RetryPolicy;
use Muscobytes\Poizon\Internal\Settings;

class OAuthClient
{
    public function __construct(private Settings $settings, private PoizonJsonTransport $http, private ResponseDecoder $decoder,
        private Backoff $backoff, private Diagnostics $diagnostics) {}

    public function authorizationUrl(#[\SensitiveParameter] string $state): string
    {
        $credentials = $this->settings->credentials();

        return 'https://open.poizon.com/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $credentials['app_key'],
            'redirect_uri' => $this->settings->redirectUri(), 'scope' => 'all', 'state' => $state], '', '&', PHP_QUERY_RFC3986);
    }

    public function prepareRefresh(): void
    {
        $this->settings->credentials();
        RetryPolicy::configured();
        $this->backoff->check(Operation::OAuthRefresh->name);
    }

    public function exchange(#[\SensitiveParameter] string $code, ?CallContext $call = null): TokenSet
    {
        return $this->token(Operation::OAuthExchange, ['authorization_code' => $code], $call);
    }

    public function refresh(#[\SensitiveParameter] string $token, ?CallContext $call = null): TokenSet
    {
        return $this->token(Operation::OAuthRefresh, ['refresh_token' => $token], $call);
    }

    private function token(Operation $operation, #[\SensitiveParameter] array $parameters, ?CallContext $call = null): TokenSet
    {
        $started = hrtime(true) / 1e6;
        $attempts = 0;
        $context = new ErrorContext($operation->name, operationId: (string) Str::uuid());
        try {
            RetryPolicy::configured();
            $credentials = $this->settings->credentials();
            $this->backoff->check($operation->name);
            $result = $this->http->post('https://open.poizon.com'.$operation->value,
                fn () => ['client_id' => $credentials['app_key'], 'client_secret' => $credentials['app_secret']] + $parameters, context: $context);
            $attempts = $result->attempts;
            $body = $this->decoder->envelope($result->response, $context);
            $context = new ErrorContext($operation->name, 'response', RequestState::ResponseReceived, httpStatus: $result->response->status(),
                upstreamCode: $body['code'], operationId: $context->operationId);
            if ($body['code'] !== 200) {
                // OAuth errors never carry data that could contain a partially valid token pair.
                throw new ApiException(new ApiErrorResponse(null,
                    new ResponseMetadata($context->operationId, $result->response->status(), $attempts, (hrtime(true) / 1e6) - $started), code: $body['code']), $context);
            }
            $data = $body['data'] ?? null;
            if (! is_array($data)) {
                throw new ProtocolException($context);
            }
            foreach (['open_id', 'access_token', 'refresh_token'] as $field) {
                if (! is_string($data[$field] ?? null) || $data[$field] === '' || strlen($data[$field]) > ($field === 'open_id' ? 255 : 4096) || preg_match('/[\x00-\x20\x7f]/', $data[$field])) {
                    throw new ProtocolException($context, field: $field, actualType: get_debug_type($data[$field] ?? null));
                }
            }
            $scope = $data['scope'] ?? null;
            $scopeType = get_debug_type($scope);
            if (is_array($scope) && array_is_list($scope)) {
                foreach ($scope as $item) {
                    if (! is_string($item) || $item === '') {
                        throw new ProtocolException($context, field: 'scope', actualType: $scopeType);
                    }
                }
                $scope = implode(' ', $scope);
            }
            if (! is_string($scope) || $scope === '' || strlen($scope) > 4096) {
                throw new ProtocolException($context, field: 'scope', actualType: $scopeType);
            }
            foreach (['access_token_expires_in', 'refresh_token_expires_in'] as $field) {
                if (! is_int($data[$field] ?? null) || $data[$field] < 1 || $data[$field] > 315360000) {
                    throw new ProtocolException($context, field: $field, actualType: get_debug_type($data[$field] ?? null));
                }
            }
            $tokens = new TokenSet($data['open_id'], $data['access_token'], $data['refresh_token'],
                now()->addSeconds($data['access_token_expires_in'])->toDateTimeImmutable(), now()->addSeconds($data['refresh_token_expires_in'])->toDateTimeImmutable(), $scope);
            $this->diagnostics->completed($context, $started, $attempts, call: $call);

            return $tokens;
        } catch (PoizonException $exception) {
            $e = $exception->context;
            $attempts = max($attempts, $e->attempts);
            $error = new ErrorContext($operation->name, $e->stage === 'preparation' ? $context->stage : $e->stage,
                $e->requestState === RequestState::NotSent ? $context->requestState : $e->requestState,
                httpStatus: $e->httpStatus ?? $context->httpStatus, upstreamCode: $e->upstreamCode ?? $context->upstreamCode,
                retryAfter: $e->retryAfter, operationId: $context->operationId, attempts: $attempts);
            $failure = $exception->withContext($error);
            $this->diagnostics->completed($error, $started, $attempts, $failure, $call);
            try {
                event(new OAuthFailed($error, class_basename($failure), $failure instanceof ProtocolException ? $failure->field : null, $failure instanceof ProtocolException ? $failure->actualType : null));
            } catch (\Throwable) {
            }
            throw $failure;
        }
    }
}
