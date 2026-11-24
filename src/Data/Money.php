<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class Money extends Record
{
    public function __construct(public string $amount,
        public int $minUnitVal,
        public string $currency,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['amount' => '!money_decimal', 'minUnitVal' => '!int', 'currency' => '!currency']);
    }
}
