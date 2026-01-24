<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\ExternalDocs;

final class ExternalDocsTest extends TestCase
{
    #[Test]
    public function json_serialize_includes_description(): void
    {
        $externalDocs = new ExternalDocs(
            url: 'https://docs.example.com',
            description: 'API documentation',
        );

        $serialized = $externalDocs->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('https://docs.example.com', $serialized['url']);
        self::assertSame('API documentation', $serialized['description']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $externalDocs = new ExternalDocs(
            url: 'https://docs.example.com',
        );

        $serialized = $externalDocs->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('url', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
    }
}
