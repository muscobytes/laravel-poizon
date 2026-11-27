<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class SkuListQuery extends Query
{
    private const SCHEMA = ['startId' => 'long', 'pageSize' => 'page', 'dwSpuId' => 'longs', 'dwSkuId' => 'longs', 'distSpuId' => 'strings', 'distSkuId' => 'strings', 'hasPrice' => 'bool', 'distPlatform' => 'string', 'modifyStartTime' => 'long', 'modifyEndTime' => 'long'];

    private array $parameters;

    public ?int $modifyStartTime;

    public ?int $modifyEndTime;

    // Keep new inputs raw until validation; weak callers otherwise coerce scalar types.
    public function __construct(#[\SensitiveParameter] public int|string|null $startId = null, #[\SensitiveParameter] public int|string|null $pageSize = null, #[\SensitiveParameter] public ?array $dwSpuId = null, #[\SensitiveParameter] public ?array $dwSkuId = null, #[\SensitiveParameter] public ?array $distSpuId = null, #[\SensitiveParameter] public ?array $distSkuId = null, #[\SensitiveParameter] public ?bool $hasPrice = null, #[\SensitiveParameter] public ?string $distPlatform = null, #[\SensitiveParameter] mixed $modifyStartTime = null, #[\SensitiveParameter] mixed $modifyEndTime = null)
    {
        $this->parameters = InputValidator::parameters(array_filter(get_defined_vars(), static fn ($v) => $v !== null), self::SCHEMA, []);
        $this->modifyStartTime = $this->parameters['modifyStartTime'] ?? null;
        $this->modifyEndTime = $this->parameters['modifyEndTime'] ?? null;
    }

    public static function fromArray(#[\SensitiveParameter] array $parameters): self
    {
        return new self(...InputValidator::parameters(array_filter($parameters, static fn ($value, $key) => $value !== null || ! in_array($key, ['modifyStartTime', 'modifyEndTime'], true), ARRAY_FILTER_USE_BOTH), self::SCHEMA, []));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
