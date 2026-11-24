<?php

namespace Muscobytes\Poizon\Data\Requests;

abstract readonly class Query
{
    abstract public function parameters(): array;
}
