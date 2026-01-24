<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Schema;

/**
 * @covers \Duyler\OpenApi\Schema\Model\Components
 */
final class ComponentsTest extends TestCase
{
    #[Test]
    public function can_create_components(): void
    {
        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        self::assertInstanceOf(Components::class, $components);
    }

    #[Test]
    public function can_create_components_with_schemas(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $components = new Components(
            schemas: ['User' => $schema],
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        self::assertArrayHasKey('User', $components->schemas);
        self::assertInstanceOf(Schema::class, $components->schemas['User']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $components = new Components(
            schemas: null,
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayNotHasKey('schemas', $serialized);
        self::assertArrayNotHasKey('responses', $serialized);
    }

    #[Test]
    public function json_serialize_includes_schemas(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: null,
        );

        $components = new Components(
            schemas: ['User' => $schema],
            responses: null,
            parameters: null,
            examples: null,
            requestBodies: null,
            headers: null,
            securitySchemes: null,
            links: null,
            callbacks: null,
            pathItems: null,
        );

        $serialized = $components->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('schemas', $serialized);
    }
}
