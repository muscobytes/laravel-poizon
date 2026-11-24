<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class SpuListData extends Record
{
    /**
     * @param  list<Spu>  $spuList
     */
    public function __construct(public array $spuList,
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
        return Hydrator::record(self::class, $values, ['spuList' => '!list:Spu', 'lastId' => 'id', 'hasMore' => 'bool', 'total' => 'int']);
    }
}
