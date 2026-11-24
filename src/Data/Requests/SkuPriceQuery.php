<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class SkuPriceQuery extends Query
{
    private array $parameters;

    public function __construct(#[\SensitiveParameter] public ?array $dwSkuIds = null, #[\SensitiveParameter] public ?bool $hasPrice = null)
    {
        $this->parameters = InputValidator::parameters(array_filter(get_defined_vars(), static fn ($v) => $v !== null), ['dwSkuIds' => 'longs', 'hasPrice' => 'bool'], ['dwSkuIds']);
    }

    public static function fromArray(#[\SensitiveParameter] array $parameters): self
    {
        return new self(...InputValidator::parameters($parameters, ['dwSkuIds' => 'longs', 'hasPrice' => 'bool'], ['dwSkuIds']));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
