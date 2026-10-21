<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Content;
use Duyler\OpenApi\Schema\Model\Example;
use Duyler\OpenApi\Schema\Model\MediaType;
use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;

use function array_keys;

#[CoversClass(Parameter::class)]
final class ParameterTest extends TestCase
{
    #[Test]
    public function can_create_parameter_with_all_fields(): void
    {
        $schema = new Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            description: 'User ID',
            required: true,
            deprecated: false,
            schema: $schema,
        );

        self::assertSame('id', $parameter->name);
        self::assertSame('path', $parameter->in);
        self::assertSame('User ID', $parameter->description);
        self::assertTrue($parameter->required);
        self::assertFalse($parameter->deprecated);
    }

    #[Test]
    public function can_create_parameter_with_null_fields(): void
    {
        $schema = new Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'page',
            in: 'query',
            description: null,
            required: false,
            deprecated: false,
            schema: $schema,
        );

        self::assertSame('page', $parameter->name);
        self::assertSame('query', $parameter->in);
        self::assertNull($parameter->description);
        self::assertFalse($parameter->required);
        self::assertFalse($parameter->deprecated);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            description: 'User ID',
            required: true,
            deprecated: false,
            schema: $schema,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayHasKey('in', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertSame('id', $serialized['name']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $schema = new Schema(
            type: 'integer',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'page',
            in: 'query',
            description: null,
            required: false,
            deprecated: false,
            schema: $schema,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('name', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
        self::assertArrayNotHasKey('deprecated', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
        );

        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            description: 'User ID',
            required: true,
            deprecated: true,
            allowEmptyValue: true,
            style: 'simple',
            explode: true,
            allowReserved: true,
            schema: $schema,
            examples: null,
            example: null,
            content: null,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertArrayHasKey('deprecated', $serialized);
        self::assertArrayHasKey('allowEmptyValue', $serialized);
        self::assertArrayHasKey('style', $serialized);
        self::assertArrayHasKey('explode', $serialized);
        self::assertArrayHasKey('allowReserved', $serialized);
        self::assertArrayHasKey('schema', $serialized);
    }

    #[Test]
    public function json_serialize_includes_examples(): void
    {
        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            examples: ['example1' => ['value' => '123']],
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('examples', $serialized);
    }

    #[Test]
    public function json_serialize_includes_example(): void
    {
        $example = new Example(
            summary: 'Example ID',
            value: 123,
        );

        $parameter = new Parameter(
            name: 'id',
            in: 'path',
            example: $example,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('example', $serialized);
    }

    #[Test]
    public function json_serialize_includes_content(): void
    {
        $content = new Content(
            mediaTypes: ['application/json' => new MediaType()],
        );

        $parameter = new Parameter(
            name: 'body',
            in: 'query',
            content: $content,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('content', $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_returns_only_ref(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/LimitParam',
            name: 'Should be ignored',
            in: 'query',
            description: 'Should be ignored',
        );

        $serialized = $parameter->jsonSerialize();

        self::assertSame(['$ref' => '#/components/parameters/LimitParam'], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_includes_summary_and_description_siblings(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/LimitParam',
            refSummary: 'Limit summary',
            refDescription: 'Limit description',
            name: 'Should be ignored',
        );

        $serialized = $parameter->jsonSerialize();

        self::assertSame([
            '$ref' => '#/components/parameters/LimitParam',
            'summary' => 'Limit summary',
            'description' => 'Limit description',
        ], $serialized);
    }

    #[Test]
    public function json_serialize_ref_object_places_ref_as_first_key(): void
    {
        $parameter = new Parameter(
            ref: '#/components/parameters/LimitParam',
            refSummary: 'Limit summary',
            refDescription: 'Limit description',
        );

        $serialized = $parameter->jsonSerialize();

        self::assertSame(['$ref', 'summary', 'description'], array_keys($serialized));
    }

    #[Test]
    public function json_serialize_emits_fields_in_wire_order(): void
    {
        $schema = new Schema(type: 'string');
        $example = new Example(summary: 'Example');
        $content = new Content(mediaTypes: ['application/json' => new MediaType()]);

        $parameter = new Parameter(
            name: 'limit',
            in: 'query',
            description: 'Limit',
            required: true,
            deprecated: true,
            allowEmptyValue: true,
            style: 'form',
            explode: true,
            allowReserved: true,
            schema: $schema,
            example: $example,
            examples: ['first' => 1],
            content: $content,
        );

        $serialized = $parameter->jsonSerialize();

        self::assertSame([
            'name' => 'limit',
            'in' => 'query',
            'description' => 'Limit',
            'required' => true,
            'deprecated' => true,
            'allowEmptyValue' => true,
            'style' => 'form',
            'explode' => true,
            'allowReserved' => true,
            'schema' => $schema,
            'example' => $example,
            'examples' => ['first' => 1],
            'content' => $content,
        ], $serialized);
    }
}
