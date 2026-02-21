<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\ExternalDocs;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Paths;
use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\Model\Servers;
use Duyler\OpenApi\Schema\Model\Tags;
use Duyler\OpenApi\Schema\Model\Webhooks;
use Duyler\OpenApi\Schema\OpenApiDocument;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OpenApiDocumentTest extends TestCase
{
    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            jsonSchemaDialect: 'https://json-schema.org/draft/2020-12/schema',
            servers: new Servers([new Server(url: 'https://api.example.com')]),
            paths: null,
            webhooks: null,
            components: null,
            security: null,
            tags: null,
            externalDocs: new ExternalDocs(url: 'https://docs.example.com'),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayHasKey('jsonSchemaDialect', $serialized);
        self::assertArrayHasKey('servers', $serialized);
        self::assertArrayHasKey('externalDocs', $serialized);
    }

    #[Test]
    public function json_serialize_excludes_null_optional_fields(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayNotHasKey('jsonSchemaDialect', $serialized);
        self::assertArrayNotHasKey('servers', $serialized);
        self::assertArrayNotHasKey('paths', $serialized);
        self::assertArrayNotHasKey('webhooks', $serialized);
        self::assertArrayNotHasKey('components', $serialized);
        self::assertArrayNotHasKey('security', $serialized);
        self::assertArrayNotHasKey('tags', $serialized);
        self::assertArrayNotHasKey('externalDocs', $serialized);
    }

    #[Test]
    public function json_serialize_with_paths(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            paths: new Paths([]),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayHasKey('paths', $serialized);
    }

    #[Test]
    public function json_serialize_with_webhooks(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            webhooks: new Webhooks([]),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayHasKey('webhooks', $serialized);
    }

    #[Test]
    public function json_serialize_with_components(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            components: new Components(),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayHasKey('components', $serialized);
    }

    #[Test]
    public function json_serialize_with_security(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            security: new SecurityRequirement([]),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayHasKey('security', $serialized);
    }

    #[Test]
    public function json_serialize_with_tags(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.1.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            tags: new Tags([]),
        );

        $serialized = $document->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('openapi', $serialized);
        self::assertArrayHasKey('info', $serialized);
        self::assertArrayHasKey('tags', $serialized);
    }

    #[Test]
    public function openapi_document_has_self_field(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            self: 'https://api.example.com/openapi.json',
        );

        self::assertSame('https://api.example.com/openapi.json', $document->self);
    }

    #[Test]
    public function json_serialize_includes_self_field(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            self: 'https://api.example.com/openapi.json',
        );

        $serialized = $document->jsonSerialize();

        self::assertArrayHasKey('$self', $serialized);
        self::assertSame('https://api.example.com/openapi.json', $serialized['$self']);
    }

    #[Test]
    public function json_serialize_excludes_null_self_field(): void
    {
        $document = new OpenApiDocument(
            openapi: '3.2.0',
            info: new InfoObject(title: 'Test API', version: '1.0.0'),
            self: null,
        );

        $serialized = $document->jsonSerialize();

        self::assertArrayNotHasKey('$self', $serialized);
    }
}
