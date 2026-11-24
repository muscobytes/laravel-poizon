<?php

namespace Muscobytes\Poizon\Data\Requests;

use Muscobytes\Poizon\Internal\InputValidator;

final readonly class OrderQuery extends Query
{
    private array $parameters;

    public function __construct(#[\SensitiveParameter] public ?array $customerOrderNo = null, #[\SensitiveParameter] public ?array $distOrderNo = null, #[\SensitiveParameter] public ?array $distSubOrderNo = null, #[\SensitiveParameter] public ?array $dwSkuId = null, #[\SensitiveParameter] public ?array $distSkuId = null, #[\SensitiveParameter] public ?array $trackingNo = null, #[\SensitiveParameter] public int|string|null $pageNum = null, #[\SensitiveParameter] public int|string|null $pageSize = null, #[\SensitiveParameter] public ?string $orderStatus = null, #[\SensitiveParameter] public int|string|null $createStartTime = null, #[\SensitiveParameter] public int|string|null $createEndTime = null, #[\SensitiveParameter] public int|string|null $modifyStartTime = null, #[\SensitiveParameter] public int|string|null $modifyEndTime = null)
    {
        $this->parameters = InputValidator::parameters(array_filter(get_defined_vars(), static fn ($v) => $v !== null), ['customerOrderNo' => 'empty_strings', 'distOrderNo' => 'empty_strings', 'distSubOrderNo' => 'empty_strings', 'dwSkuId' => 'empty_longs', 'distSkuId' => 'empty_strings', 'trackingNo' => 'empty_strings', 'pageNum' => 'positive', 'pageSize' => 'order_page', 'orderStatus' => 'string', 'createStartTime' => 'long', 'createEndTime' => 'long', 'modifyStartTime' => 'long', 'modifyEndTime' => 'long'], []);
    }

    public static function fromArray(#[\SensitiveParameter] array $parameters): self
    {
        return new self(...InputValidator::parameters($parameters, ['customerOrderNo' => 'empty_strings', 'distOrderNo' => 'empty_strings', 'distSubOrderNo' => 'empty_strings', 'dwSkuId' => 'empty_longs', 'distSkuId' => 'empty_strings', 'trackingNo' => 'empty_strings', 'pageNum' => 'positive', 'pageSize' => 'order_page', 'orderStatus' => 'string', 'createStartTime' => 'long', 'createEndTime' => 'long', 'modifyStartTime' => 'long', 'modifyEndTime' => 'long'], []));
    }

    public function parameters(): array
    {
        return $this->parameters;
    }
}
