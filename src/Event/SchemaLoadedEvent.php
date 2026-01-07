<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

use Duyler\OpenApi\Schema\OpenApiDocument;

readonly class SchemaLoadedEvent
{
    public function __construct(
        public readonly OpenApiDocument $document,
        public readonly string $source,
    ) {}
}
