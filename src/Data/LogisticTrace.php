<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class LogisticTrace extends Record
{
    /**
     * @param  list<LogisticEvent>|null  $traceList
     * @param  list<LogisticEvent>|null  $logisticTraceList
     */
    public function __construct(public ?string $waybillNo = null,
        public ?string $logisticsCompany = null,
        public ?array $traceList = null,
        public ?array $logisticTraceList = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['waybillNo' => 'string', 'logisticsCompany' => 'string', 'traceList' => 'list:LogisticEvent', 'logisticTraceList' => 'list:LogisticEvent']);
    }
}
