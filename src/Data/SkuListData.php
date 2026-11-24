<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class SkuListData extends Record
{
    /**
     * @param  list<Sku>  $skuList
     */
    public function __construct(public array $skuList,
        public ?string $lastId = null,
        public ?bool $hasMore = null,
        public ?int $total = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['skuList' => '!list:Sku', 'lastId' => 'id', 'hasMore' => 'bool', 'total' => 'int']);
    }
}
