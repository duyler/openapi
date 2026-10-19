<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\TypeHelper;

/** @internal */
final readonly class ArraySchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     *
     * @return array{
     *     items: Schema|bool|null,
     *     prefixItems: ?list<Schema>,
     *     contains: Schema|bool|null,
     *     minContains: ?int,
     *     maxContains: ?int,
     *     minItems: ?int,
     *     maxItems: ?int,
     *     uniqueItems: ?bool,
     *     unevaluatedItems: Schema|bool|null,
     * }
     */
    public function build(array $data, Closure $recurse): array
    {
        $accumulator = new SchemaKeywordAccumulator($recurse);

        return [
            'items' => $accumulator->schemaOrBoolOrNull($data, 'items'),
            'prefixItems' => $accumulator->schemaList($data, 'prefixItems'),
            'contains' => $accumulator->schemaOrBoolOrNull($data, 'contains'),
            'minContains' => TypeHelper::asIntOrNull($data['minContains'] ?? null),
            'maxContains' => TypeHelper::asIntOrNull($data['maxContains'] ?? null),
            'minItems' => TypeHelper::asIntOrNull($data['minItems'] ?? null),
            'maxItems' => TypeHelper::asIntOrNull($data['maxItems'] ?? null),
            'uniqueItems' => TypeHelper::asBoolOrNull($data['uniqueItems'] ?? null),
            'unevaluatedItems' => $accumulator->schemaOrBoolOrNull($data, 'unevaluatedItems'),
        ];
    }
}
