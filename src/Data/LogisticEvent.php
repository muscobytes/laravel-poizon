<?php

namespace Muscobytes\Poizon\Data;

use Muscobytes\Poizon\Internal\Hydrator;

final readonly class LogisticEvent extends Record
{
    public function __construct(public ?string $time = null,
        public ?string $description = null,
        public ?string $status = null,
        public ?string $context = null,
        array $extraFields = [],
        array $presentFields = [])
    {
        parent::__construct($extraFields, $presentFields);
    }

    public static function fromArray(#[\SensitiveParameter] array $values): self
    {
        return Hydrator::record(self::class, $values, ['time' => 'string', 'description' => 'string', 'status' => 'string', 'context' => 'string']);
    }
}
