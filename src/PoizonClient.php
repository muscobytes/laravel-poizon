<?php

namespace Muscobytes\Poizon;

use Illuminate\Support\Str;
use Muscobytes\Poizon\Data\ApiErrorResponse;
use Muscobytes\Poizon\Data\ApiResponse;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\BalanceResponse;
use Muscobytes\Poizon\Data\CallContext;
use Muscobytes\Poizon\Data\CreateOrderData;
use Muscobytes\Poizon\Data\CreateOrderResponse;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Data\ErrorData;
use Muscobytes\Poizon\Data\LogisticsResponse;
use Muscobytes\Poizon\Data\OrderInfoResponse;
use Muscobytes\Poizon\Data\Requests\CreateOrderRequest;
use Muscobytes\Poizon\Data\Requests\OrderQuery;
use Muscobytes\Poizon\Data\Requests\SkuDetailQuery;
use Muscobytes\Poizon\Data\Requests\SkuListQuery;
use Muscobytes\Poizon\Data\Requests\SkuPriceQuery;
use Muscobytes\Poizon\Data\Requests\SpuListQuery;
use Muscobytes\Poizon\Data\ResponseMetadata;
use Muscobytes\Poizon\Data\SkuDetailResponse;
use Muscobytes\Poizon\Data\SkuListResponse;
use Muscobytes\Poizon\Data\SkuPricesResponse;
use Muscobytes\Poizon\Data\SpuListResponse;
use Muscobytes\Poizon\Enums\Operation;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Exceptions\ApiException;
use Muscobytes\Poizon\Exceptions\AuthorizationException;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Internal\Backoff;
use Muscobytes\Poizon\Internal\Diagnostics;
use Muscobytes\Poizon\Internal\InputValidator;
use Muscobytes\Poizon\Internal\Redactor;
use Muscobytes\Poizon\Internal\ResponseDecoder;
use Muscobytes\Poizon\Internal\RetryPolicy;
use Muscobytes\Poizon\Internal\Settings;
use Muscobytes\Poizon\Services\AuthorizationManager;
use Muscobytes\Poizon\Services\PoizonJsonTransport;
use Muscobytes\Poizon\Services\PoizonOpenPlatformSigner;

class PoizonClient
{
    public function __construct(private AuthorizationManager $credentials, private PoizonOpenPlatformSigner $signer,
        private PoizonJsonTransport $http, private Settings $settings, private Backoff $backoff,
        private ResponseDecoder $decoder, private Diagnostics $diagnostics) {}

    public function queryPrepaymentBalance(AuthorizationReference $authorization, ?CallContext $context = null): BalanceResponse
    {
        return $this->request($authorization, Operation::Balance, [], $context);
    }

    public function querySkuList(AuthorizationReference $authorization, SkuListQuery $request = new SkuListQuery, ?CallContext $context = null): SkuListResponse
    {
        return $this->request($authorization, Operation::SkuList, $request->parameters(), $context);
    }

    public function querySpuList(AuthorizationReference $authorization, SpuListQuery $request = new SpuListQuery, ?CallContext $context = null): SpuListResponse
    {
        return $this->request($authorization, Operation::SpuList, $request->parameters(), $context);
    }

    public function querySkuDetail(AuthorizationReference $authorization, SkuDetailQuery $request, ?CallContext $context = null): SkuDetailResponse
    {
        return $this->request($authorization, Operation::SkuDetail, $request->parameters(), $context);
    }

    public function querySkuBidPrice(AuthorizationReference $authorization, SkuPriceQuery $request, ?CallContext $context = null): SkuPricesResponse
    {
        return $this->request($authorization, Operation::SkuPrices, $request->parameters(), $context);
    }

    public function queryOrderInfo(AuthorizationReference $authorization, OrderQuery $request = new OrderQuery, ?CallContext $context = null): OrderInfoResponse
    {
        return $this->request($authorization, Operation::OrderInfo, $request->parameters(), $context);
    }

    public function createOrder(AuthorizationReference $authorization, CreateOrderRequest $request, ?CallContext $context = null): CreateOrderResponse
    {
        return $this->request($authorization, Operation::CreateOrder, $request->parameters(), $context);
    }

    public function queryLogisticsTrace(AuthorizationReference $authorization, string $customerOrderNo, ?CallContext $context = null): LogisticsResponse
    {
        return $this->request($authorization, Operation::Logistics, ['customerOrderNo' => InputValidator::string($customerOrderNo)], $context);
    }

    private function request(AuthorizationReference $reference, Operation $operation, #[\SensitiveParameter] array $parameters, ?CallContext $call): ApiResponse
    {
        $started = hrtime(true) / 1e6;
        $operationId = (string) Str::uuid();
        $attempts = 0;
        $context = new ErrorContext($operation->name, connection: $reference->connection, authorizationId: $reference->authorizationId, operationId: $operationId);
        try {
            RetryPolicy::configured();
            $configuration = $this->settings->openPlatform();
            $this->backoff->check($operation->name);
            $credential = $this->credentials->resolve($reference, new CallContext($call?->correlationId, $operationId));
            $token = $credential->accessToken();
            $context = new ErrorContext($operation->name, connection: $reference->connection, authorizationId: $credential->authorizationId, operationId: $operationId);
            $envelope = [];
            $result = $this->http->post('https://open.poizon.com'.$operation->value, function () use ($parameters, $configuration, $token, &$envelope): array {
                $envelope = $parameters + ['app_key' => $configuration['app_key'], 'access_token' => $token,
                    'timestamp' => now()->getTimestampMs(), 'language' => $configuration['language'], 'timeZone' => $configuration['time_zone']];
                $envelope['sign'] = $this->signer->sign($envelope, $configuration['app_secret']);

                return $envelope;
            }, $operation->allowsRetry(), context: $context);
            $attempts = $result->attempts;
            $context = new ErrorContext($operation->name, 'response', RequestState::ResponseReceived, $reference->connection,
                $credential->authorizationId, $result->response->status(), operationId: $operationId);
            try {
                $body = $this->decoder->envelope($result->response, $context);
            } catch (AuthorizationException $exception) {
                try {
                    $this->credentials->rejectAccessTokenForAuthorization($credential->authorizationId, $token);
                } catch (AuthorizationException) { /* Revocation reports the expected authorization requirement. */
                }
                throw $exception;
            }
            $context = new ErrorContext($operation->name, 'response', RequestState::ResponseReceived, $reference->connection,
                $credential->authorizationId, $result->response->status(), $body['code'], operationId: $operationId);
            $safe = Redactor::clean($body, [$configuration['app_key'], $configuration['app_secret'], $token, $envelope['sign']]);
            $metadata = new ResponseMetadata($operationId, $result->response->status(), $attempts, (hrtime(true) / 1e6) - $started);
            $message = is_string($safe['msg'] ?? null) ? $safe['msg'] : null;
            if ($body['code'] !== 200) {
                $data = isset($safe['data']) ? ($operation === Operation::CreateOrder ? CreateOrderData::fromArray($safe['data']) : ErrorData::fromArray($safe['data'])) : null;
                throw new ApiException(new ApiErrorResponse($data, $metadata, $message, $body['code']), $context);
            }
            if (! is_array($safe['data'] ?? null)) {
                throw new ProtocolException($context);
            }
            $dataClass = $operation->dataClass();
            $responseClass = $operation->responseClass();
            $response = new $responseClass($dataClass::fromArray($safe['data']), $metadata, $message);
            $this->diagnostics->completed($context, $started, $attempts, call: $call);

            return $response;
        } catch (PoizonException $exception) {
            $e = $exception->context;
            $attempts = max($attempts, $e->attempts);
            $failureContext = new ErrorContext($operation->name, $e->stage === 'preparation' ? $context->stage : $e->stage,
                $e->requestState === RequestState::NotSent ? $context->requestState : $e->requestState,
                $reference->connection, $context->authorizationId, $e->httpStatus ?? $context->httpStatus,
                $e->upstreamCode ?? $context->upstreamCode, $e->retryAfter, $operationId, $attempts);
            $failure = $exception->withContext($failureContext);
            $this->diagnostics->completed($failureContext, $started, $attempts, $failure, $call);
            throw $failure;
        }
    }
}
