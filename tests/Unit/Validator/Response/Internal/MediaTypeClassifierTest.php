<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Response\Internal;

use Duyler\OpenApi\Validator\Response\Internal\MediaTypeClassifier;
use Duyler\OpenApi\Validator\Response\Internal\MediaTypeKind;
use Duyler\OpenApi\Validator\SchemaValidator\Enum\ContentMediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(MediaTypeClassifier::class)]
final class MediaTypeClassifierTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: MediaTypeKind}>
     */
    public static function provide_classification_vectors(): array
    {
        return [
            'application/json is complete' => ['application/json', MediaTypeKind::Complete],
            'application/xml is complete' => ['application/xml', MediaTypeKind::Complete],
            'text/plain is complete' => ['text/plain', MediaTypeKind::Complete],
            'text/html is complete' => ['text/html', MediaTypeKind::Complete],
            'text/xml is complete' => ['text/xml', MediaTypeKind::Complete],
            'image/svg+xml is complete' => ['image/svg+xml', MediaTypeKind::Complete],
            'multipart/form-data is complete' => ['multipart/form-data', MediaTypeKind::Complete],
            'application/pdf is complete' => ['application/pdf', MediaTypeKind::Complete],
            'image/png is complete' => ['image/png', MediaTypeKind::Complete],
            'image/jpeg is complete' => ['image/jpeg', MediaTypeKind::Complete],
            'image/gif is complete' => ['image/gif', MediaTypeKind::Complete],
            'application/x-www-form-urlencoded is complete' => ['application/x-www-form-urlencoded', MediaTypeKind::Complete],
            'application/octet-stream is binary stream' => ['application/octet-stream', MediaTypeKind::BinaryStream],
            'application/jsonl is sequential' => ['application/jsonl', MediaTypeKind::Sequential],
            'application/x-ndjson is sequential' => ['application/x-ndjson', MediaTypeKind::Sequential],
            'application/json-seq is sequential' => ['application/json-seq', MediaTypeKind::Sequential],
            'application/geo+json-seq is sequential' => ['application/geo+json-seq', MediaTypeKind::Sequential],
            'text/event-stream is sequential' => ['text/event-stream', MediaTypeKind::Sequential],
            'multipart/mixed is sequential' => ['multipart/mixed', MediaTypeKind::Sequential],
            'unknown media type defaults to complete' => ['application/vnd.custom', MediaTypeKind::Complete],
            'parameterized json stays complete' => ['application/json; charset=utf-8', MediaTypeKind::Complete],
            'multipart form data with boundary stays complete' => ['multipart/form-data; boundary=xyz', MediaTypeKind::Complete],
            'parameterized jsonl stays sequential' => ['application/jsonl; boundary=frame', MediaTypeKind::Sequential],
            'parameterized event stream stays sequential' => ['text/event-stream; charset=utf-8', MediaTypeKind::Sequential],
            'parameterized octet stream stays binary stream' => ['application/octet-stream; q=1', MediaTypeKind::BinaryStream],
            'octet stream with quoted event stream is sequential' => ['application/octet-stream; charset="text/event-stream"', MediaTypeKind::Sequential],
            'octet stream with quoted ndjson is sequential' => ['application/octet-stream; x="application/x-ndjson"', MediaTypeKind::Sequential],
            'vendor suffixed json-seq is not sequential' => ['application/vnd.foo+json-seq', MediaTypeKind::Complete],
        ];
    }

    #[Test]
    #[DataProvider('provide_classification_vectors')]
    public function classify_resolves_expected_kind(string $mediaType, MediaTypeKind $expectedKind): void
    {
        self::assertSame($expectedKind, MediaTypeClassifier::classify($mediaType));
    }

    #[Test]
    public function classify_covers_every_content_pipeline_media_type(): void
    {
        foreach (ContentMediaType::cases() as $contentMediaType) {
            $expectedKind = ContentMediaType::ApplicationOctetStream === $contentMediaType
                ? MediaTypeKind::BinaryStream
                : MediaTypeKind::Complete;

            self::assertSame(
                $expectedKind,
                MediaTypeClassifier::classify($contentMediaType->value),
                sprintf('Media type %s must resolve to a classification kind', $contentMediaType->value),
            );
        }
    }

    #[Test]
    public function sequential_text_stream_stays_outside_content_pipeline_media_types(): void
    {
        self::assertSame(MediaTypeKind::Sequential, MediaTypeClassifier::classify('text/event-stream'));
        self::assertNull(ContentMediaType::tryFrom('text/event-stream'));
        self::assertNull(ContentMediaType::tryFrom('multipart/mixed'));
    }
}
