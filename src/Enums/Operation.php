<?php

namespace Muscobytes\Poizon\Enums;

use Muscobytes\Poizon\Data\BalanceData;
use Muscobytes\Poizon\Data\BalanceResponse;
use Muscobytes\Poizon\Data\CreateOrderData;
use Muscobytes\Poizon\Data\CreateOrderResponse;
use Muscobytes\Poizon\Data\LogisticsData;
use Muscobytes\Poizon\Data\LogisticsResponse;
use Muscobytes\Poizon\Data\OrderInfoData;
use Muscobytes\Poizon\Data\OrderInfoResponse;
use Muscobytes\Poizon\Data\SkuDetailResponse;
use Muscobytes\Poizon\Data\SkuListData;
use Muscobytes\Poizon\Data\SkuListResponse;
use Muscobytes\Poizon\Data\SkuPriceData;
use Muscobytes\Poizon\Data\SkuPricesResponse;
use Muscobytes\Poizon\Data\SpuListData;
use Muscobytes\Poizon\Data\SpuListResponse;

enum Operation: string
{
    case Balance = '/dop/api/v1/pop/api/v1/market/channel/order/queryPrepaymentBalance';
    case SkuList = '/dop/api/v1/pop/api/v1/market/channel/product/querySkuList';
    case SpuList = '/dop/api/v1/pop/api/v1/market/channel/product/querySpuList';
    case SkuDetail = '/dop/api/v1/pop/api/v1/market/channel/product/querySkuDetail';
    case SkuPrices = '/dop/api/v1/pop/api/v1/market/channel/product/querySkuBidPrice';
    case CreateOrder = '/dop/api/v1/pop/api/v1/market/channel/order/createOrder';
    case OrderInfo = '/dop/api/v1/pop/api/v1/market/channel/order/queryOrderInfo';
    case Logistics = '/dop/api/v1/pop/api/v1/market/channel/order/queryLogisticsTrace';
    case OAuthExchange = '/api/v1/h5/passport/v1/oauth2/token';
    case OAuthRefresh = '/api/v1/h5/passport/v1/oauth2/refresh_token';
    case Authorize = 'authorize';
    case SyncRead = '/api/admin/market/check';

    public function allowsRetry(): bool
    {
        return ! in_array($this, [self::CreateOrder, self::OAuthExchange, self::OAuthRefresh, self::Authorize], true);
    }

    public function responseClass(): string
    {
        return match ($this) {
            self::Balance => BalanceResponse::class,
            self::SkuList => SkuListResponse::class,
            self::SpuList => SpuListResponse::class,
            self::SkuDetail => SkuDetailResponse::class,
            self::SkuPrices => SkuPricesResponse::class,
            self::CreateOrder => CreateOrderResponse::class,
            self::OrderInfo => OrderInfoResponse::class,
            self::Logistics => LogisticsResponse::class,
            default => throw new \LogicException('Operation has no Open Platform response.'),
        };
    }

    public function dataClass(): string
    {
        return match ($this) {
            self::Balance => BalanceData::class,
            self::SkuList => SkuListData::class,
            self::SpuList => SpuListData::class,
            self::SkuDetail => SkuListData::class,
            self::SkuPrices => SkuPriceData::class,
            self::CreateOrder => CreateOrderData::class,
            self::OrderInfo => OrderInfoData::class,
            self::Logistics => LogisticsData::class,
            default => throw new \LogicException('Operation has no Open Platform data.'),
        };
    }
}
