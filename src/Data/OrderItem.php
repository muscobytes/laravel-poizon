<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class OrderItem extends Record
{
    public function __construct(public ?string $requestId = null,
        public ?string $customerOrderNo = null,
        public ?string $distOrderNo = null,
        public ?string $distSubOrderNo = null,
        public ?string $dwSkuId = null,
        public ?string $dwSpuId = null,
        public ?string $distSkuId = null,
        public ?string $distSpuId = null,
        public ?int $quantity = null,
        public ?int $maxQuantity = null,
        public ?string $orderStatus = null,
        public ?string $failMessage = null,
        public ?string $trackingNo = null,
        public ?string $headTrackingNo = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['quantity' => 'int', 'requestId' => 'string', 'customerOrderNo' => 'string', 'distOrderNo' => 'string', 'distSubOrderNo' => 'string', 'dwSkuId' => 'id', 'dwSpuId' => 'id', 'distSkuId' => 'string', 'distSpuId' => 'string', 'maxQuantity' => 'int', 'orderStatus' => 'string', 'failMessage' => 'string', 'trackingNo' => 'string', 'headTrackingNo' => 'string']);
    }
}
