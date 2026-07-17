<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator\Enum;

use Duyler\OpenApi\Validator\SchemaValidator\Enum\ContentMediaType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentMediaType::class)]
final class ContentMediaTypeTest extends TestCase
{
    #[Test]
    public function cases_contain_all_supported_media_types(): void
    {
        $values = array_map(
            static fn(ContentMediaType $type): string => $type->value,
            ContentMediaType::cases(),
        );

        self::assertSame(
            [
                'application/json',
                'application/xml',
                'text/plain',
                'text/html',
                'text/xml',
                'image/svg+xml',
                'multipart/form-data',
                'application/pdf',
                'application/octet-stream',
                'image/png',
                'image/jpeg',
                'image/gif',
                'application/x-www-form-urlencoded',
            ],
            $values,
        );
    }

    #[Test]
    public function try_from_returns_case_for_known_media_type(): void
    {
        self::assertSame(
            ContentMediaType::ApplicationJson,
            ContentMediaType::tryFrom('application/json'),
        );
        self::assertSame(
            ContentMediaType::ApplicationXml,
            ContentMediaType::tryFrom('application/xml'),
        );
    }

    #[Test]
    public function try_from_returns_null_for_unknown_media_type(): void
    {
        self::assertNull(ContentMediaType::tryFrom('application/vnd.custom+json'));
        self::assertNull(ContentMediaType::tryFrom(''));
    }
}
