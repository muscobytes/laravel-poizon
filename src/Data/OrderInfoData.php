<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class OrderInfoData extends Record
{
    /**
     * @param  list<OrderItem>  $orderList
     */
    public function __construct(public array $orderList,
        public ?int $total = null,
        public ?int $pageNum = null,
        public ?int $pageSize = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['orderList' => '!list:OrderItem', 'total' => 'int', 'pageNum' => 'int', 'pageSize' => 'int']);
    }
}
