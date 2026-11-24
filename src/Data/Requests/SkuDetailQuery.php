<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class SkuDetailQuery extends Query
{
    private array $parameters;

    public function __construct(#[\SensitiveParameter] public int|string|null $dwSpuId = null, #[\SensitiveParameter] public ?string $distSpuId = null, #[\SensitiveParameter] public ?array $dwSkuId = null, #[\SensitiveParameter] public ?array $distSkuId = null, #[\SensitiveParameter] public ?string $distPlatform = null)
    {
        $this->parameters = InputValidator::parameters(array_filter(get_defined_vars(), static fn ($v) => $v !== null), ['dwSpuId' => 'long', 'distSpuId' => 'string', 'dwSkuId' => 'longs', 'distSkuId' => 'strings', 'distPlatform' => 'string'], []);
    }

    public static function fromArray(#[\SensitiveParameter] array $parameters): self
    {
        return new self(...InputValidator::parameters($parameters, ['dwSpuId' => 'long', 'distSpuId' => 'string', 'dwSkuId' => 'longs', 'distSkuId' => 'strings', 'distPlatform' => 'string'], []));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
