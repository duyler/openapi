<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal
 *
 * @param list<Schema> $chainResources
 */
final readonly class DynamicScopeFrame
{
    public function __construct(
        public readonly Schema $schema,
        public readonly array $chainResources,
        public readonly bool $entersWrapperScope,
        public readonly ?Schema $companion = null,
    ) {}
}
