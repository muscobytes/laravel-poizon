<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class SkuListQuery extends Query
{
    private array $parameters;

    public function __construct(#[\SensitiveParameter] public int|string|null $startId = null, #[\SensitiveParameter] public int|string|null $pageSize = null, #[\SensitiveParameter] public ?array $dwSpuId = null, #[\SensitiveParameter] public ?array $dwSkuId = null, #[\SensitiveParameter] public ?array $distSpuId = null, #[\SensitiveParameter] public ?array $distSkuId = null, #[\SensitiveParameter] public ?bool $hasPrice = null, #[\SensitiveParameter] public ?string $distPlatform = null)
    {
        $this->parameters = InputValidator::parameters(array_filter(get_defined_vars(), static fn ($v) => $v !== null), ['startId' => 'long', 'pageSize' => 'page', 'dwSpuId' => 'longs', 'dwSkuId' => 'longs', 'distSpuId' => 'strings', 'distSkuId' => 'strings', 'hasPrice' => 'bool', 'distPlatform' => 'string'], []);
    }

    public static function fromArray(#[\SensitiveParameter] array $parameters): self
    {
        return new self(...InputValidator::parameters($parameters, ['startId' => 'long', 'pageSize' => 'page', 'dwSpuId' => 'longs', 'dwSkuId' => 'longs', 'distSpuId' => 'strings', 'distSkuId' => 'strings', 'hasPrice' => 'bool', 'distPlatform' => 'string'], []));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
