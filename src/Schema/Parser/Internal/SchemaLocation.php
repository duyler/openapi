<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

/** @internal */
final readonly class SchemaLocation
{
    public function __construct(
        public readonly Schema $schema,
        public readonly string $pointer,
    ) {}
}
