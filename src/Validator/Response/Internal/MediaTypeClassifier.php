<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Response\Internal;

use function str_contains;

/** @internal */
final readonly class MediaTypeClassifier
{
    public const string APPLICATION_JSONL = 'application/jsonl';

    public const string APPLICATION_X_NDJSON = 'application/x-ndjson';

    public const string APPLICATION_JSON_SEQ = 'application/json-seq';

    public const string TEXT_EVENT_STREAM = 'text/event-stream';

    /** @var array<string, MediaTypeKind> */
    private const array CLASSIFICATION = [
        'application/geo+json-seq' => MediaTypeKind::Sequential,
        'application/json' => MediaTypeKind::Complete,
        self::APPLICATION_JSON_SEQ => MediaTypeKind::Sequential,
        self::APPLICATION_JSONL => MediaTypeKind::Sequential,
        'application/octet-stream' => MediaTypeKind::BinaryStream,
        'application/pdf' => MediaTypeKind::Complete,
        self::APPLICATION_X_NDJSON => MediaTypeKind::Sequential,
        'application/x-www-form-urlencoded' => MediaTypeKind::Complete,
        'application/xml' => MediaTypeKind::Complete,
        'image/gif' => MediaTypeKind::Complete,
        'image/jpeg' => MediaTypeKind::Complete,
        'image/png' => MediaTypeKind::Complete,
        'image/svg+xml' => MediaTypeKind::Complete,
        'multipart/form-data' => MediaTypeKind::Complete,
        'multipart/mixed' => MediaTypeKind::Sequential,
        self::TEXT_EVENT_STREAM => MediaTypeKind::Sequential,
        'text/html' => MediaTypeKind::Complete,
        'text/plain' => MediaTypeKind::Complete,
        'text/xml' => MediaTypeKind::Complete,
    ];

    public static function classify(string $mediaType): MediaTypeKind
    {
        return self::CLASSIFICATION[$mediaType] ?? self::classifyByFamily($mediaType);
    }

    private static function classifyByFamily(string $mediaType): MediaTypeKind
    {
        return self::findByKind($mediaType, MediaTypeKind::Sequential)
            ?? self::findByKind($mediaType, MediaTypeKind::BinaryStream)
            ?? MediaTypeKind::Complete;
    }

    private static function findByKind(string $mediaType, MediaTypeKind $kind): ?MediaTypeKind
    {
        foreach (self::CLASSIFICATION as $type => $tableKind) {
            if ($tableKind !== $kind) {
                continue;
            }

            if (str_contains($mediaType, $type)) {
                return $kind;
            }
        }

        return null;
    }
}
