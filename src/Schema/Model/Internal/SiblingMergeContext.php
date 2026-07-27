<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @internal
 */
final readonly class SiblingMergeContext
{
    public function __construct(
        public Schema $resolved,
        public Schema $sibling,
    ) {}
}
