<?php

namespace Muscobytes\Poizon\Internal;

use Closure;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Muscobytes\Poizon\Data\ErrorContext;
use Muscobytes\Poizon\Data\TokenSet;
use Muscobytes\Poizon\Enums\CredentialFailure;
use Muscobytes\Poizon\Exceptions\ConfigurationException;
use Muscobytes\Poizon\Exceptions\CredentialStoreException;
use Muscobytes\Poizon\Exceptions\PoizonException;
use Muscobytes\Poizon\Exceptions\ProtocolException;
use Muscobytes\Poizon\Exceptions\UnexpectedIntegrationException;
use Muscobytes\Poizon\Models\PoizonAuthorization;
use Muscobytes\Poizon\Models\PoizonConnection;

final class CredentialStore
{
    private const MUTEX = 'poizon-open-credentials';

    public function synchronized(#[\SensitiveParameter] Closure $callback): mixed
    {
        return $this->protect(function () use ($callback) {
            return $this->locked($callback);
        });
    }

    public function connections(#[\SensitiveParameter] array $names): Collection
    {
        return $this->protect(fn () => PoizonConnection::with('authorization')->whereIn('direction', $names)->get());
    }

    private function locked(#[\SensitiveParameter] Closure $callback): mixed
    {
        if (DB::transactionLevel() > 0) {
            throw new ConfigurationException;
        }
        foreach (['connection', 'lock_connection'] as $setting) {
            $connection = config('cache.stores.database.'.$setting);
            if ($connection !== null && $connection !== config('database.default')) {
                throw new ConfigurationException;
            }
        }
        $lock = Cache::store('database')->lock(self::MUTEX, (int) config('poizon.open_platform.mutex_seconds', 120));

        return $lock->block((int) config('poizon.open_platform.wait_seconds', 5), fn () => $callback($lock));
    }

    private function protect(#[\SensitiveParameter] Closure $callback): mixed
    {
        try {
            return $callback();
        } catch (LockTimeoutException) {
            throw new CredentialStoreException(CredentialFailure::LockTimeout, new ErrorContext(stage: 'mutex'));
        } catch (PoizonException $exception) {
            throw $exception;
        } catch (EncryptException|DecryptException) {
            throw new CredentialStoreException(CredentialFailure::Encryption, new ErrorContext(stage: 'storage'));
        } catch (QueryException $e) {
            $state = (string) $e->getCode();
            throw new CredentialStoreException(CredentialFailure::Database, new ErrorContext(stage: 'storage'), preg_match('/^[0-9A-Z]{5}$/D', $state) ? $state : null);
        } catch (\Throwable) {
            throw new UnexpectedIntegrationException(new ErrorContext(stage: 'storage'));
        }
    }

    public function transaction(Lock $lock, #[\SensitiveParameter] Closure $callback): mixed
    {
        return DB::transaction(function () use ($lock, $callback) {
            $row = DB::table(config('cache.stores.database.lock_table') ?: 'cache_locks')
                ->where('key', Cache::store('database')->getPrefix().self::MUTEX)->lockForUpdate()->first();
            if (! $row || ! hash_equals($row->owner, $lock->owner()) || $row->expiration <= now()->timestamp) {
                throw new CredentialStoreException(CredentialFailure::LockLost, new ErrorContext(stage: 'mutex'));
            }

            return $callback();
        });
    }

    public function attributes(#[\SensitiveParameter] TokenSet $tokens): array
    {
        if ($tokens->accessExpiresAt->getTimestamp() <= now()->timestamp || $tokens->refreshExpiresAt->getTimestamp() <= now()->timestamp) {
            throw new ProtocolException;
        }

        return ['open_id' => $tokens->openId, 'access_token' => $tokens->accessToken(), 'refresh_token' => $tokens->refreshToken(),
            'access_token_expires_at' => $tokens->accessExpiresAt, 'refresh_token_expires_at' => $tokens->refreshExpiresAt, 'scope' => $tokens->scope];
    }

    public function save(Lock $lock, string $connection, #[\SensitiveParameter] TokenSet $tokens): void
    {
        $this->transaction($lock, function () use ($connection, $tokens): void {
            $authorization = PoizonAuthorization::firstOrNew(['open_id' => $tokens->openId]);
            $authorization->fill($this->attributes($tokens) + ['refresh_pending' => false, 'refresh_attempt' => null,
                'status' => 'active', 'problem_reason' => null, 'problem_notified_at' => null, 'problem_notification_status' => null])->save();
            PoizonConnection::updateOrCreate(['direction' => $connection], ['poizon_authorization_id' => $authorization->id]);
        });
    }
}
