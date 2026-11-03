<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

final readonly class VocabularyDisabledWarningEvent
{
    public function __construct(
        public readonly string $vocabularyUri,
    ) {}
}
