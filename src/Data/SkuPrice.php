<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class SkuPrice extends Record
{
    public function __construct(public ?string $dwSkuId = null,
        public ?string $distSkuId = null,
        public ?string $minBidPrice = null,
        public ?string $fulfillmentMode = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['dwSkuId' => 'id', 'distSkuId' => 'string', 'minBidPrice' => 'decimal', 'fulfillmentMode' => 'string']);
    }
}
