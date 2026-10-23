<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/** @internal */
final readonly class DynamicScopeFrame
{
    /**
     * @param list<Schema> $chainResources
     */
    public function __construct(
        public readonly Schema $schema,
        public readonly array $chainResources,
        public readonly bool $entersWrapperScope,
    ) {}
}
