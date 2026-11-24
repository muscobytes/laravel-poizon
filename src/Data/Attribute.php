<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class Attribute extends Record
{
    public function __construct(public ?string $attrName = null,
        public ?string $attrValue = null,
        public ?string $name = null,
        public ?string $value = null,
        public ?string $enName = null, public ?string $enValue = null, array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['enValue' => 'string', 'enName' => 'string', 'attrName' => 'string', 'attrValue' => 'string', 'name' => 'string', 'value' => 'string']);
    }
}
