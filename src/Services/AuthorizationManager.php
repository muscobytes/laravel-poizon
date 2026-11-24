<?php

namespace Muscobytes\Poizon\Services;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Str;
use Muscobytes\Poizon\Contracts\AuthorizationRequiredNotifier;
use Muscobytes\Poizon\Data\AuthorizationReference;
use Muscobytes\Poizon\Data\AuthorizationStatus;
use Muscobytes\Poizon\Data\AuthorizationStatuses;
use Muscobytes\Poizon\Data\CallContext;
use Muscobytes\Poizon\Data\CredentialSnapshot;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Data\TokenSet;
use Muscobytes\Poizon\Enums\AuthorizationReason;
use Muscobytes\Poizon\Enums\CredentialFailure;
use Muscobytes\Poizon\Enums\RequestState;
use Muscobytes\Poizon\Events\OAuthFailed;
use Muscobytes\Poizon\Exceptions\AuthorizationException;
use Muscobytes\Poizon\Exceptions\ConfigurationException;
use Muscobytes\Poizon\Exceptions\CredentialStoreException;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Exceptions\RateLimitException;
use Muscobytes\Poizon\Internal\CredentialStore;
use Muscobytes\Poizon\Internal\Diagnostics;
use Muscobytes\Poizon\Models\PoizonAuthorization;
use Muscobytes\Poizon\Models\PoizonConnection;
use Muscobytes\Poizon\Support\ConnectionName;
use SensitiveParameter;
use Throwable;

class AuthorizationManager
{
    public function __construct(private OAuthClient $oauth, private AuthorizationRequiredNotifier $notifications, private CredentialStore $store, private Diagnostics $diagnostics) {}

    public function prepare(): void
    {
        $this->store->synchronized(fn (Lock $lock) => $this->store->transaction($lock, fn () => PoizonConnection::query()->count()));
    }

    public function authorize(#[SensitiveParameter] string $connection, #[SensitiveParameter] string $code, ?CallContext $call = null): void
    {
        $this->assertDirection($connection);
        $started = hrtime(true) / 1e6;
        $id = (string) Str::uuid();
        $context = new ErrorContext('Authorize', connection: $connection, operationId: $id);
        try {
            $this->store->synchronized(function (Lock $lock) use ($connection, $code, $call, $id, &$context): void {
                $tokens = $this->oauth->exchange($code, new CallContext($call?->correlationId, $id));
                $context = new ErrorContext('Authorize', 'storage', RequestState::ResponseReceived, $connection, operationId: $id);
                $this->store->save($lock, $connection, $tokens);
            });
            $this->diagnostics->completed($context, $started, 1, call: $call);
        } catch (PoizonException $exception) {
            $e = $exception->context;
            $context = new ErrorContext('Authorize', $e->stage === 'preparation' ? $context->stage : $e->stage,
                $e->requestState === RequestState::NotSent ? $context->requestState : $e->requestState, $connection,
                httpStatus: $e->httpStatus, upstreamCode: $e->upstreamCode, retryAfter: $e->retryAfter, operationId: $id,
                attempts: max($e->attempts, $context->requestState === RequestState::NotSent ? 0 : 1));
            $failure = $exception->withContext($context);
            $this->diagnostics->completed($context, $started, $context->attempts, $failure, $call);
            if ($exception instanceof CredentialStoreException) {
                try {
                    event(new OAuthFailed(new ErrorContext('OAuthExchange', $exception->context->stage,
                        $context->requestState, $connection, operationId: $id, attempts: $context->attempts),
                        class_basename($exception), storageReason: $exception->reason->value, sqlState: $exception->sqlState));
                } catch (Throwable) {
                }
            }
            throw $failure;
        }
    }

    public function save(#[SensitiveParameter] string $connection, #[SensitiveParameter] TokenSet $account): void
    {
        $this->assertDirection($connection);
        $this->store->synchronized(fn (Lock $lock) => $this->store->save($lock, $connection, $account));
    }

    public function resolve(AuthorizationReference $reference, ?CallContext $call = null): CredentialSnapshot
    {
        return $this->store->synchronized(function (Lock $lock) use ($reference, $call): CredentialSnapshot {
            $account = $reference->authorizationId === null
                ? PoizonConnection::where('direction', $reference->connection)->first()?->authorization
                : PoizonAuthorization::find($reference->authorizationId);
            if (! $account) {
                throw new AuthorizationException(AuthorizationReason::Missing);
            }

            return new CredentialSnapshot($account->id, $this->ensureFresh($lock, $account, 0, $call));
        });
    }

    /** @internal */
    public function accessToken(#[SensitiveParameter] string $connection): string
    {
        $this->assertDirection($connection);

        return $this->store->synchronized(function (Lock $lock) use ($connection): string {
            // Always reload after waiting, including a rebind performed by OAuth.
            $authorization = PoizonConnection::where('direction', $connection)->first()?->authorization;
            if (! $authorization) {
                throw new AuthorizationException(AuthorizationReason::Missing);
            }

            return $this->ensureFresh($lock, $authorization, 0);
        });
    }

    /** @internal */
    public function rejectAccessToken(#[SensitiveParameter] string $connection, #[SensitiveParameter] string $token): void
    {
        $this->assertDirection($connection);
        $this->store->synchronized(function (Lock $lock) use ($connection, $token): void {
            $authorization = PoizonConnection::where('direction', $connection)->first()?->authorization;
            if (! $authorization || ! hash_equals($authorization->access_token, $token)) {
                // A late rejection of the previous pair must not revoke a newer OAuth or refresh.
                throw new CredentialStoreException(CredentialFailure::LockLost);
            }
            $this->requireOAuth($lock, $authorization, AuthorizationReason::Rejected);
        });
    }

    public function authorizationId(#[SensitiveParameter] string $connection): int
    {
        $this->assertDirection($connection);

        return $this->store->synchronized(function (Lock $lock) use ($connection): int {
            $authorization = PoizonConnection::where('direction', $connection)->first()?->authorization;
            if (! $authorization) {
                throw new AuthorizationException(AuthorizationReason::Missing);
            }
            $this->ensureFresh($lock, $authorization, 0);

            return $authorization->id;
        });
    }

    /** @internal */
    public function accessTokenForAuthorization(int $authorizationId): string
    {
        return $this->store->synchronized(function (Lock $lock) use ($authorizationId): string {
            $authorization = PoizonAuthorization::find($authorizationId);
            if (! $authorization) {
                throw new AuthorizationException(AuthorizationReason::Missing);
            }

            return $this->ensureFresh($lock, $authorization, 0);
        });
    }

    /** @internal */
    public function rejectAccessTokenForAuthorization(int $authorizationId, #[SensitiveParameter] string $token): void
    {
        $this->store->synchronized(function (Lock $lock) use ($authorizationId, $token): void {
            $authorization = PoizonAuthorization::find($authorizationId);
            if (! $authorization || ! hash_equals($authorization->access_token, $token)) {
                throw new CredentialStoreException(CredentialFailure::LockLost);
            }
            $this->requireOAuth($lock, $authorization, AuthorizationReason::Rejected);
        });
    }

    public function refreshDue(): void
    {
        $this->store->synchronized(function (Lock $lock): void {
            $ids = PoizonConnection::query()->distinct()->pluck('poizon_authorization_id');
            foreach (PoizonAuthorization::whereIn('id', $ids)->get() as $authorization) {
                try {
                    $this->ensureFresh($lock, $authorization, (int) config('poizon.open_platform.refresh_ahead_seconds', 300));
                } catch (AuthorizationException) { /* Other accounts can still be refreshed. */
                }
            }
        });
    }

    public function statuses(#[SensitiveParameter] array $requested): AuthorizationStatuses
    {
        foreach ($requested as $name) {
            ConnectionName::validate($name);
        }
        $connections = $this->store->connections($requested)->keyBy('direction');
        $statuses = [];
        foreach ($requested as $name) {
            $account = $connections->get($name)?->authorization;
            $status = $account === null ? 'missing' : match (true) {
                $account->status !== 'active' => 'reauthorization_required',
                $account->refresh_pending => 'refresh_pending',
                $account->refresh_token_expires_at->isPast() => 'reauthorization_required',
                $account->access_token_expires_at->lte(now()->addSeconds((int) config('poizon.open_platform.refresh_ahead_seconds', 300))) => 'refresh_due',
                default => 'connected',
            };
            $statuses[$name] = new AuthorizationStatus($status, $account?->open_id,
                $account?->access_token_expires_at, $account?->refresh_token_expires_at,
                $account?->problem_reason === null ? null : AuthorizationReason::tryFrom($account->problem_reason), $account?->problem_notification_status);
        }

        return new AuthorizationStatuses($statuses);
    }

    private function ensureFresh(Lock $lock, #[SensitiveParameter] PoizonAuthorization $authorization, int $ahead, ?CallContext $call = null): string
    {
        if ($authorization->status !== 'active') {
            if ($authorization->problem_notification_status === 'sending') {
                $this->store->transaction($lock, fn () => $authorization->forceFill(['problem_notification_status' => 'unknown'])->save());
            }
            throw new AuthorizationException(AuthorizationReason::tryFrom($authorization->problem_reason ?? '') ?? AuthorizationReason::RefreshUncertain);
        }
        if ($authorization->refresh_pending) {
            $this->requireOAuth($lock, $authorization, AuthorizationReason::RefreshUncertain);
        }
        if ($authorization->refresh_token_expires_at->lte(now())) {
            $this->requireOAuth($lock, $authorization, AuthorizationReason::RefreshExpired);
        }
        if ($authorization->access_token_expires_at->gt(now()->addSeconds($ahead))) {
            return $authorization->access_token;
        }

        $this->oauth->prepareRefresh();
        $refreshToken = $authorization->refresh_token;
        $attempt = (string) Str::uuid();
        $this->store->transaction($lock, function () use ($authorization, $attempt): void {
            $authorization->forceFill(['refresh_pending' => true, 'refresh_attempt' => $attempt])->save();
        });
        try {
            // The durable marker commits before HTTP; an interrupted rotation is never retried.
            $account = $this->oauth->refresh($refreshToken, $call);
            if ($account->openId !== $authorization->open_id) {
                throw new ProtocolException;
            }
            $this->store->transaction($lock, function () use ($authorization, $account, $attempt): void {
                $current = PoizonAuthorization::lockForUpdate()->findOrFail($authorization->id);
                if ($current->refresh_attempt !== $attempt || ! $current->refresh_pending) {
                    throw new CredentialStoreException(CredentialFailure::LockLost);
                }
                $current->fill($this->store->attributes($account))->forceFill(['refresh_pending' => false, 'refresh_attempt' => null])->save();
            });

            return $account->accessToken();
        } catch (CredentialStoreException $exception) {
            if ($exception->reason === CredentialFailure::LockLost) {
                throw $exception;
            }
            $this->requireOAuth($lock, $authorization, AuthorizationReason::RefreshUncertain);
        } catch (ConfigurationException|RateLimitException $exception) {
            if ($exception->context->requestState === RequestState::NotSent) {
                $this->store->transaction($lock, fn () => $authorization->forceFill(['refresh_pending' => false, 'refresh_attempt' => null])->save());
                throw $exception;
            }
            $this->requireOAuth($lock, $authorization, AuthorizationReason::RefreshUncertain);
        } catch (AuthorizationException $exception) {
            $this->requireOAuth($lock, $authorization, $exception->reason);
        } catch (Throwable) {
            // The durable marker prevents repeating a rotation whose result cannot be persisted.
            $this->requireOAuth($lock, $authorization, AuthorizationReason::RefreshUncertain);
        }
    }

    private function requireOAuth(Lock $lock, #[SensitiveParameter] PoizonAuthorization $authorization, AuthorizationReason $reason): never
    {
        $notify = $this->store->transaction($lock, function () use ($authorization, $reason): bool {
            $current = PoizonAuthorization::lockForUpdate()->findOrFail($authorization->id);
            $notify = $current->problem_notified_at === null;
            $current->forceFill([
                'status' => 'reauthorization_required',
                'problem_reason' => $reason->value,
                'problem_notified_at' => $current->problem_notified_at ?? now(),
                'problem_notification_status' => $notify ? 'sending' : $current->problem_notification_status,
            ])->save();

            return $notify;
        });
        if ($notify) {
            $connections = PoizonConnection::where('poizon_authorization_id', $authorization->id)->pluck('direction')->all();
            try {
                $sent = $this->notifications->notify($authorization->id, $connections, $reason->value);
            } catch (Throwable) {
                $sent = false;
            }
            $this->store->transaction($lock, function () use ($authorization, $sent): void {
                $current = PoizonAuthorization::lockForUpdate()->findOrFail($authorization->id);
                $current->forceFill(['problem_notification_status' => $sent ? 'sent' : 'unknown'])->save();
            });
        }
        throw new AuthorizationException($reason);
    }

    private function assertDirection(#[SensitiveParameter] string $connection): void
    {
        ConnectionName::validate($connection);
    }
}
