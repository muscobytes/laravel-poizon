<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class SizeInfo extends Record
{
    public function __construct(public ?string $sizeKey = null,
        public ?string $sizeValue = null,
        public ?string $size = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['sizeKey' => 'string', 'sizeValue' => 'string', 'size' => 'string']);
    }
}
