<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

final readonly class LegacyKeywordWarningEvent
{
    public function __construct(
        public readonly string $legacyKeyword,
        public readonly string $migrationKeyword,
    ) {}
}
