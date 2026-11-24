<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class CreateOrderData extends Record
{
    /**
     * @param  list<OrderItem>|null  $orderItems
     * @param  list<OrderItem>|null  $subOrderList
     */
    public function __construct(public ?string $requestId = null,
        public ?string $customerOrderNo = null,
        public ?string $distOrderNo = null,
        public ?array $orderItems = null,
        public ?array $subOrderList = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['requestId' => 'string', 'customerOrderNo' => 'string', 'distOrderNo' => 'string', 'orderItems' => 'list:OrderItem', 'subOrderList' => 'list:OrderItem']);
    }
}
