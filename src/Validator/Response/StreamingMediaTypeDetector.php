<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use function str_contains;

final readonly class StreamingMediaTypeDetector
{
    public static function isStreaming(string $mediaType): bool
    {
        return null !== self::detect($mediaType);
    }

    public static function detect(string $mediaType): ?StreamingFormat
    {
        return match (true) {
            str_contains($mediaType, 'application/jsonl'),
            str_contains($mediaType, 'application/x-ndjson') => StreamingFormat::NdJson,
            str_contains($mediaType, 'text/event-stream') => StreamingFormat::Sse,
            str_contains($mediaType, 'application/json-seq') => StreamingFormat::JsonSeq,
            default => null,
        };
    }
}
