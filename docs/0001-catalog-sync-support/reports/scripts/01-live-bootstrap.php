<?php

use App\Services\PoizonJsonTransport;
use App\Services\PoizonOpenPlatformSigner;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

// Stream this bootstrap and an experiment body to PHP inside the existing app container.
// It prints nothing: experiment bodies must output only an explicit safe field allowlist.
chdir('/var/www/html');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$app['config']->set('app.debug', false);
Http::swap(new Factory);

$poizonRead = static function (string $direction, string $operation, array $business) use ($app): array {
    if (! in_array($direction, ['fast', 'global'], true)
        || ! in_array($operation, ['querySpuList', 'querySkuList', 'querySkuDetail', 'querySkuBidPrice'], true)
        || array_intersect(array_keys($business), ['app_key', 'app_secret', 'access_token', 'sign', 'timestamp'])) {
        throw new RuntimeException('Unsafe experiment request.');
    }
    $db = DB::connection();
    $db->beginTransaction();
    try {
        $db->statement('SET TRANSACTION READ ONLY');
        $credential = $db->table('poizon_connections as c')
            ->join('poizon_authorizations as a', 'a.id', '=', 'c.poizon_authorization_id')
            ->where('c.direction', $direction)
            ->select('a.access_token', 'a.status', 'a.access_token_expires_at', 'a.refresh_pending')
            ->first();
        if (! $credential || $credential->status !== 'active' || $credential->refresh_pending
            || new DateTimeImmutable($credential->access_token_expires_at) <= new DateTimeImmutable('+5 minutes')) {
            throw new RuntimeException('Read-only credentials unavailable.');
        }
        $token = $app['encrypter']->decrypt($credential->access_token, false);
    } finally {
        $db->rollBack();
    }
    $settings = config('raketa.poizon.open_platform');
    if (! is_string($token) || $token === '' || empty($settings['app_key']) || empty($settings['app_secret'])) {
        throw new RuntimeException('Read-only credentials unavailable.');
    }
    $signer = new PoizonOpenPlatformSigner;
    $transport = new PoizonJsonTransport;
    $response = $transport->post(
        'https://open.poizon.com/dop/api/v1/pop/api/v1/market/channel/product/'.$operation,
        static function () use ($business, $settings, $token, $signer): array {
            $payload = $business + [
                'app_key' => $settings['app_key'], 'access_token' => $token,
                'timestamp' => (int) floor(microtime(true) * 1000),
                'language' => $settings['language'] ?? 'en', 'timeZone' => $settings['time_zone'] ?? 'Asia/Shanghai',
            ];
            $payload['sign'] = $signer->sign($payload, $settings['app_secret']);

            return $payload;
        },
        retryRead: false,
    );
    $body = json_decode($response->body(), true, flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR);
    if ($response->status() === 429) {
        throw new RuntimeException('Rate limited; stop and respect Retry-After.');
    }

    return ['http' => $response->status(), 'attempts' => 1, 'body' => $body];
};
