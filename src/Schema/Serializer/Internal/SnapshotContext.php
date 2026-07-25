<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Serializer\Internal;

use Closure;
use Duyler\OpenApi\Schema\Model\Schema;

use WeakMap;

/** @internal */
final readonly class SnapshotContext
{
    /**
     * @param WeakMap<Schema, int> $visited
     * @param Closure(Schema, WeakMap<Schema, int>): array $recurseSnapshot
     */
    public function __construct(
        public WeakMap $visited,
        public Closure $recurseSnapshot,
    ) {}
}
