<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal
 */
final readonly class CompositionFields
{
    /**
     * @param list<Schema>|null   $allOf
     * @param list<Schema>|null   $anyOf
     * @param list<Schema>|null   $oneOf
     */
    public function __construct(
        public ?array $allOf = null,
        public ?array $anyOf = null,
        public ?array $oneOf = null,
        public Schema|bool|null $not = null,
        public Schema|bool|null $if = null,
        public Schema|bool|null $then = null,
        public Schema|bool|null $else = null,
    ) {}
}
