<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

final readonly class StreamingMediaTypeDetector
{
    private const array STREAMING_TYPES = [
        'text/event-stream',
        'application/jsonl',
        'application/x-ndjson',
        'application/json-seq',
    ];

    public static function isStreaming(string $mediaType): bool
    {
        return array_any(self::STREAMING_TYPES, fn(string $type) => str_starts_with($mediaType, $type));
    }
}
