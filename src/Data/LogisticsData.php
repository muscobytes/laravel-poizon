<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class LogisticsData extends Record
{
    /**
     * @param  list<SubOrderTrace>  $subOrderTraceInfo
     */
    public function __construct(
        public array $subOrderTraceInfo, public ?string $customerOrderNo = null,
        public ?string $distOrderNo = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['customerOrderNo' => 'string', 'distOrderNo' => 'string', 'subOrderTraceInfo' => '!list:SubOrderTrace']);
    }
}
