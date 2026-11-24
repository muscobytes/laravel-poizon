<?php

namespace Muscobytes\Poizon\Data;

abstract readonly class Record
{
    public function __construct(public array $extraFields = [], private array $presentFields = []) {}

    public function fields(): array
    {
        return $this->presentFields;
    }

    public function has(string $field): bool
    {
        return in_array($field, $this->presentFields, true);
    }
}
