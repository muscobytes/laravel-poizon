<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class SpuListQuery extends Query
{
    private const SCHEMA = ['startId' => 'long', 'pageSize' => 'page', 'dwSpuId' => 'longs', 'distSpuId' => 'strings', 'distPlatform' => 'string', 'querySku' => 'bool', 'modifyStartTime' => 'long', 'modifyEndTime' => 'long'];

    private array $parameters;

    public ?bool $querySku;

    public ?int $modifyStartTime;

    public ?int $modifyEndTime;

    // Keep new inputs raw until validation; weak callers otherwise coerce scalar types.
    public function __construct(#[\SensitiveParameter] public int|string|null $startId = null, #[\SensitiveParameter] public int|string|null $pageSize = null, #[\SensitiveParameter] public ?array $dwSpuId = null, #[\SensitiveParameter] public ?array $distSpuId = null, #[\SensitiveParameter] public ?string $distPlatform = null, #[\SensitiveParameter] mixed $querySku = null, #[\SensitiveParameter] mixed $modifyStartTime = null, #[\SensitiveParameter] mixed $modifyEndTime = null)
    {
        $this->parameters = InputValidator::parameters(array_filter(get_defined_vars(), static fn ($v) => $v !== null), self::SCHEMA, []);
        $this->querySku = $this->parameters['querySku'] ?? null;
        $this->modifyStartTime = $this->parameters['modifyStartTime'] ?? null;
        $this->modifyEndTime = $this->parameters['modifyEndTime'] ?? null;
    }

    public static function fromArray(#[\SensitiveParameter] array $parameters): self
    {
        return new self(...InputValidator::parameters(array_filter($parameters, static fn ($value, $key) => $value !== null || ! in_array($key, ['querySku', 'modifyStartTime', 'modifyEndTime'], true), ARRAY_FILTER_USE_BOTH), self::SCHEMA, []));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
