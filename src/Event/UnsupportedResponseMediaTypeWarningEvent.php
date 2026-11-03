<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Event;

final readonly class UnsupportedResponseMediaTypeWarningEvent
{
    public function __construct(
        public readonly string $mediaType,
    ) {}

    public static function forType(string $mediaType): self
    {
        return new self(mediaType: $mediaType);
    }
}
