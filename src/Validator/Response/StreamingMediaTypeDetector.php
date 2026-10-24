<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response;

use Duyler\OpenApi\Validator\Response\Internal\MediaTypeClassifier;
use Duyler\OpenApi\Validator\Response\Internal\MediaTypeKind;

use function str_contains;

final readonly class StreamingMediaTypeDetector
{
    public static function isStreaming(string $mediaType): bool
    {
        return null !== self::detect($mediaType);
    }

    public static function detect(string $mediaType): ?StreamingFormat
    {
        if (MediaTypeKind::Sequential !== MediaTypeClassifier::classify($mediaType)) {
            return null;
        }

        return match (true) {
            str_contains($mediaType, 'application/jsonl'),
            str_contains($mediaType, 'application/x-ndjson') => StreamingFormat::NdJson,
            str_contains($mediaType, 'text/event-stream') => StreamingFormat::Sse,
            str_contains($mediaType, 'application/json-seq') => StreamingFormat::JsonSeq,
            default => null,
        };
    }
}
