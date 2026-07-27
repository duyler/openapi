<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal
 */
final readonly class ArrayFields
{
    /**
     * @param list<Schema>|null   $prefixItems
     */
    public function __construct(
        public Schema|bool|null $items = null,
        public ?array $prefixItems = null,
        public ?int $minItems = null,
        public ?int $maxItems = null,
        public ?bool $uniqueItems = null,
        public Schema|bool|null $contains = null,
        public ?int $minContains = null,
        public ?int $maxContains = null,
        public Schema|bool|null $unevaluatedItems = null,
    ) {}
}
