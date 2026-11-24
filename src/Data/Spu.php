<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class Spu extends Record
{
    /**
     * @param  list<Sku>|null  $skuList
     */
    public function __construct(public ?string $dwSpuId = null,
        public ?string $distSpuId = null,
        public ?string $dwSpuTitle = null,
        public ?string $distSpuTitle = null,
        public ?string $distBrandName = null,
        public ?string $title = null,
        public ?string $image = null,
        public ?array $skuList = null,
        public ?string $styleId = null, array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['styleId' => 'string', 'dwSpuId' => 'id', 'distSpuId' => 'string', 'dwSpuTitle' => 'string', 'distSpuTitle' => 'string', 'distBrandName' => 'string', 'title' => 'string', 'image' => 'string', 'skuList' => 'list:Sku']);
    }
}
