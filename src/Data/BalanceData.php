<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class BalanceData extends Record
{
    public function __construct(public Money $imoneyDTO,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['imoneyDTO' => '!Money']);
    }
}
