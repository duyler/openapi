<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Schema;

/** @internal */
final readonly class ResourceSchemaKeywordParser
{
    /**
     * @param array<array-key, mixed> $data
     * @param Closure(bool|array): Schema $recurse
     * @return array{defs: ?array<string, Schema>}
     */
    public function build(array $data, Closure $recurse): array
    {
        $accumulator = new SchemaKeywordAccumulator($recurse);

        return [
            'defs' => $accumulator->schemaMap($data, '$defs'),
        ];
    }
}
