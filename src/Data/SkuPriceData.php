<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class SkuPriceData extends Record
{
    /**
     * @param  list<SkuPrice>  $items
     */
    public function __construct(public array $items,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['items' => '!list:SkuPrice']);
    }
}
