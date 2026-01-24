<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Schema\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;

#[CoversClass(Schema::class)]
final class SchemaTest extends TestCase
{
    #[Test]
    public function can_create_schema_with_type(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
        );

        self::assertSame('object', $schema->type);
        self::assertArrayHasKey('id', $schema->properties);
    }

    #[Test]
    public function can_create_schema_with_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
            required: ['id'],
            description: 'User schema',
        );

        self::assertSame('object', $schema->type);
        self::assertArrayHasKey('id', $schema->properties);
        self::assertContains('id', $schema->required);
        self::assertSame('User schema', $schema->description);
    }

    #[Test]
    public function can_create_schema_with_null_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
            required: null,
            description: null,
        );

        self::assertSame('string', $schema->type);
        self::assertNull($schema->properties);
        self::assertNull($schema->required);
        self::assertNull($schema->description);
    }

    #[Test]
    public function json_serialize_includes_all_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
            required: ['id'],
            description: 'User schema',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayHasKey('properties', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertSame('object', $serialized['type']);
    }

    #[Test]
    public function json_serialize_excludes_null_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            properties: null,
            required: null,
            description: null,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayNotHasKey('properties', $serialized);
        self::assertArrayNotHasKey('required', $serialized);
        self::assertArrayNotHasKey('description', $serialized);
    }

    #[Test]
    public function json_serialize_includes_all_optional_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['id' => ['type' => 'integer']],
            required: ['id'],
            description: 'User schema',
            title: 'User',
            default: null,
            deprecated: true,
            const: null,
            multipleOf: null,
            maximum: null,
            exclusiveMaximum: null,
            minimum: null,
            exclusiveMinimum: null,
            maxLength: null,
            minLength: null,
            pattern: null,
            maxItems: null,
            minItems: null,
            uniqueItems: null,
            maxProperties: null,
            minProperties: null,
            allOf: null,
            anyOf: null,
            oneOf: null,
            not: null,
            discriminator: null,
            additionalProperties: null,
            unevaluatedProperties: null,
            items: null,
            prefixItems: null,
            contains: null,
            minContains: null,
            maxContains: null,
            patternProperties: null,
            propertyNames: null,
            dependentSchemas: null,
            if: null,
            then: null,
            else: null,
            unevaluatedItems: null,
            example: null,
            examples: null,
            enum: null,
            format: null,
            contentEncoding: null,
            contentMediaType: null,
            contentSchema: null,
            jsonSchemaDialect: null,
            ref: null,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('type', $serialized);
        self::assertArrayHasKey('properties', $serialized);
        self::assertArrayHasKey('required', $serialized);
        self::assertArrayHasKey('description', $serialized);
        self::assertArrayHasKey('title', $serialized);
        self::assertArrayHasKey('deprecated', $serialized);
    }

    #[Test]
    public function json_serialize_includes_format(): void
    {
        $schema = new Schema(
            type: 'string',
            format: 'email',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('format', $serialized);
    }

    #[Test]
    public function json_serialize_includes_default(): void
    {
        $schema = new Schema(
            type: 'string',
            default: 'example',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('default', $serialized);
    }

    #[Test]
    public function json_serialize_includes_ref(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('$ref', $serialized);
    }

    #[Test]
    public function json_serialize_includes_allOf(): void
    {
        $schema = new Schema(
            allOf: [
                new Schema(type: 'string'),
                new Schema(type: 'number'),
            ],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('allOf', $serialized);
    }

    #[Test]
    public function json_serialize_includes_anyOf(): void
    {
        $schema = new Schema(
            anyOf: [
                new Schema(type: 'string'),
                new Schema(type: 'number'),
            ],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('anyOf', $serialized);
    }

    #[Test]
    public function json_serialize_includes_oneOf(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'number'),
            ],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('oneOf', $serialized);
    }

    #[Test]
    public function json_serialize_includes_not(): void
    {
        $schema = new Schema(
            not: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('not', $serialized);
    }

    #[Test]
    public function json_serialize_includes_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('items', $serialized);
    }

    #[Test]
    public function json_serialize_includes_enum(): void
    {
        $schema = new Schema(
            type: 'string',
            enum: ['red', 'green', 'blue'],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('enum', $serialized);
    }

    #[Test]
    public function json_serialize_includes_const(): void
    {
        $schema = new Schema(
            type: 'string',
            const: 'fixed value',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('const', $serialized);
    }

    #[Test]
    public function json_serialize_includes_multipleOf(): void
    {
        $schema = new Schema(
            type: 'number',
            multipleOf: 3,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('multipleOf', $serialized);
    }

    #[Test]
    public function json_serialize_includes_maximum(): void
    {
        $schema = new Schema(
            type: 'number',
            maximum: 100,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('maximum', $serialized);
    }

    #[Test]
    public function json_serialize_includes_minimum(): void
    {
        $schema = new Schema(
            type: 'number',
            minimum: 0,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('minimum', $serialized);
    }

    #[Test]
    public function json_serialize_includes_exclusiveMaximum(): void
    {
        $schema = new Schema(
            type: 'number',
            exclusiveMaximum: 100,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('exclusiveMaximum', $serialized);
    }

    #[Test]
    public function json_serialize_includes_exclusiveMinimum(): void
    {
        $schema = new Schema(
            type: 'number',
            exclusiveMinimum: 0,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('exclusiveMinimum', $serialized);
    }

    #[Test]
    public function json_serialize_includes_maxLength(): void
    {
        $schema = new Schema(
            type: 'string',
            maxLength: 100,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('maxLength', $serialized);
    }

    #[Test]
    public function json_serialize_includes_minLength(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 1,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('minLength', $serialized);
    }

    #[Test]
    public function json_serialize_includes_pattern(): void
    {
        $schema = new Schema(
            type: 'string',
            pattern: '^[a-z]+$',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('pattern', $serialized);
    }

    #[Test]
    public function json_serialize_includes_maxItems(): void
    {
        $schema = new Schema(
            type: 'array',
            maxItems: 10,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('maxItems', $serialized);
    }

    #[Test]
    public function json_serialize_includes_minItems(): void
    {
        $schema = new Schema(
            type: 'array',
            minItems: 1,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('minItems', $serialized);
    }

    #[Test]
    public function json_serialize_includes_uniqueItems(): void
    {
        $schema = new Schema(
            type: 'array',
            uniqueItems: true,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('uniqueItems', $serialized);
    }

    #[Test]
    public function json_serialize_includes_maxProperties(): void
    {
        $schema = new Schema(
            type: 'object',
            maxProperties: 10,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('maxProperties', $serialized);
    }

    #[Test]
    public function json_serialize_includes_minProperties(): void
    {
        $schema = new Schema(
            type: 'object',
            minProperties: 1,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('minProperties', $serialized);
    }

    #[Test]
    public function json_serialize_includes_additionalProperties(): void
    {
        $schema = new Schema(
            type: 'object',
            additionalProperties: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('additionalProperties', $serialized);
    }

    #[Test]
    public function json_serialize_includes_unevaluatedProperties(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: true,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('unevaluatedProperties', $serialized);
    }

    #[Test]
    public function json_serialize_includes_prefixItems(): void
    {
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(type: 'string'),
                new Schema(type: 'number'),
            ],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('prefixItems', $serialized);
    }

    #[Test]
    public function json_serialize_includes_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'number'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contains', $serialized);
    }

    #[Test]
    public function json_serialize_includes_minContains(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'number'),
            minContains: 1,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contains', $serialized);
        self::assertArrayHasKey('minContains', $serialized);
    }

    #[Test]
    public function json_serialize_includes_maxContains(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'number'),
            maxContains: 5,
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contains', $serialized);
        self::assertArrayHasKey('maxContains', $serialized);
    }

    #[Test]
    public function json_serialize_includes_patternProperties(): void
    {
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^S_' => new Schema(type: 'string'),
            ],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('patternProperties', $serialized);
    }

    #[Test]
    public function json_serialize_includes_propertyNames(): void
    {
        $schema = new Schema(
            type: 'object',
            propertyNames: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('propertyNames', $serialized);
    }

    #[Test]
    public function json_serialize_includes_dependentSchemas(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'foo' => new Schema(type: 'string'),
            ],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('dependentSchemas', $serialized);
    }

    #[Test]
    public function json_serialize_includes_if(): void
    {
        $schema = new Schema(
            if: new Schema(type: 'number'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('if', $serialized);
    }

    #[Test]
    public function json_serialize_includes_then(): void
    {
        $schema = new Schema(
            then: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('then', $serialized);
    }

    #[Test]
    public function json_serialize_includes_else(): void
    {
        $schema = new Schema(
            else: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('else', $serialized);
    }

    #[Test]
    public function json_serialize_includes_unevaluatedItems(): void
    {
        $schema = new Schema(
            type: 'array',
            unevaluatedItems: new Schema(type: 'string'),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('unevaluatedItems', $serialized);
    }

    #[Test]
    public function json_serialize_includes_example(): void
    {
        $schema = new Schema(
            type: 'string',
            example: 'hello world',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('example', $serialized);
    }

    #[Test]
    public function json_serialize_includes_examples(): void
    {
        $schema = new Schema(
            type: 'string',
            examples: ['hello', 'world'],
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('examples', $serialized);
    }

    #[Test]
    public function json_serialize_includes_contentEncoding(): void
    {
        $schema = new Schema(
            type: 'string',
            contentEncoding: 'base64',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contentEncoding', $serialized);
    }

    #[Test]
    public function json_serialize_includes_contentMediaType(): void
    {
        $schema = new Schema(
            type: 'string',
            contentMediaType: 'application/json',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contentMediaType', $serialized);
    }

    #[Test]
    public function json_serialize_includes_contentSchema(): void
    {
        $schema = new Schema(
            type: 'string',
            contentSchema: 'https://example.com/schema',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('contentSchema', $serialized);
    }

    #[Test]
    public function json_serialize_includes_jsonSchemaDialect(): void
    {
        $schema = new Schema(
            type: 'object',
            jsonSchemaDialect: 'https://json-schema.org/draft/2020-12/schema',
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('$schema', $serialized);
    }

    #[Test]
    public function json_serialize_includes_discriminator(): void
    {
        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'type',
                mapping: null,
            ),
        );

        $serialized = $schema->jsonSerialize();

        self::assertIsArray($serialized);
        self::assertArrayHasKey('discriminator', $serialized);
    }
}
