<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class Sku extends Record
{
    /**
     * @param  list<Attribute>|null  $saleAttr
     * @param  list<SizeInfo>|null  $sizeInfos
     */
    public function __construct(public ?string $dwSkuId = null,
        public ?string $dwSpuId = null,
        public ?string $distSkuId = null,
        public ?string $distSpuId = null,
        public ?string $minBidPrice = null,
        public ?string $fulfillmentMode = null,
        public ?string $title = null,
        public ?string $image = null,
        public ?string $skuLink = null,
        public ?array $saleAttr = null,
        public ?array $sizeInfos = null,
        public ?Spu $spuBasicAttr = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['dwSkuId' => 'id', 'dwSpuId' => 'id', 'distSkuId' => 'string', 'distSpuId' => 'string', 'minBidPrice' => 'decimal', 'fulfillmentMode' => 'string', 'title' => 'string', 'image' => 'string', 'skuLink' => 'string', 'saleAttr' => 'list:Attribute', 'sizeInfos' => 'list:SizeInfo', 'spuBasicAttr' => 'Spu']);
    }
}
