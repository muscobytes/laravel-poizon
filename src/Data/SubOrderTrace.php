<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class SubOrderTrace extends Record
{
    public function __construct(public ?string $distSubOrderNo = null,
        public ?string $dwSkuId = null,
        public ?string $distSkuId = null,
        public ?LogisticTrace $headLogisticTraceInfo = null,
        public ?LogisticTrace $tailLogisticTraceInfo = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['distSubOrderNo' => 'string', 'dwSkuId' => 'id', 'distSkuId' => 'string', 'headLogisticTraceInfo' => 'LogisticTrace', 'tailLogisticTraceInfo' => 'LogisticTrace']);
    }
}
