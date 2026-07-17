<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaFromArrayConverter::class)]
final class SchemaFromArrayConverterTest extends TestCase
{
    #[Test]
    public function boolean_true_returns_empty_schema(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(true);

        self::assertEquals(new Schema(), $schema);
    }

    #[Test]
    public function boolean_false_returns_schema_with_not_empty(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(false);

        self::assertNotNull($schema->not);
        self::assertEquals(new Schema(), $schema->not);
    }

    #[Test]
    public function round_trip_simple_string_schema(): void
    {
        $converter = new SchemaFromArrayConverter();
        $data = ['type' => 'string', 'minLength' => 3, 'maxLength' => 50];

        $schema = $converter->fromArray($data);

        self::assertSame('string', $schema->type);
        self::assertSame(3, $schema->minLength);
        self::assertSame(50, $schema->maxLength);
    }

    #[Test]
    public function round_trip_object_schema_with_properties(): void
    {
        $converter = new SchemaFromArrayConverter();
        $data = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name'],
        ];

        $schema = $converter->fromArray($data);

        self::assertSame('object', $schema->type);
        self::assertNotNull($schema->properties);
        self::assertArrayHasKey('name', $schema->properties);
        self::assertSame('string', $schema->properties['name']->type);
        self::assertSame(['name'], $schema->required);
    }

    #[Test]
    public function multiple_of_zero_throws_invalid_schema_exception(): void
    {
        $converter = new SchemaFromArrayConverter();

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('multipleOf MUST be strictly greater than 0, got 0');

        $converter->fromArray(['type' => 'integer', 'multipleOf' => 0]);
    }

    #[Test]
    public function multiple_of_negative_throws_invalid_schema_exception(): void
    {
        $converter = new SchemaFromArrayConverter();

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('multipleOf MUST be strictly greater than 0, got -1');

        $converter->fromArray(['type' => 'integer', 'multipleOf' => -1]);
    }

    #[Test]
    public function invalid_xml_node_type_throws_invalid_schema_exception(): void
    {
        $converter = new SchemaFromArrayConverter();

        $this->expectException(InvalidSchemaException::class);
        $this->expectExceptionMessage('Invalid XML nodeType "invalid"');

        $converter->fromArray([
            'type' => 'string',
            'xml' => ['nodeType' => 'invalid'],
        ]);
    }

    #[Test]
    public function has_default_set_when_default_key_present_with_null_value(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(['type' => 'string', 'default' => null]);

        self::assertTrue($schema->hasDefault);
        self::assertNull($schema->default);
    }

    #[Test]
    public function has_default_unset_when_default_key_absent(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(['type' => 'string']);

        self::assertFalse($schema->hasDefault);
    }

    #[Test]
    public function has_const_set_when_const_key_present(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(['type' => 'string', 'const' => 'fixed']);

        self::assertTrue($schema->hasConst);
        self::assertSame('fixed', $schema->const);
    }

    #[Test]
    public function dollar_ref_populates_ref_summary_and_description(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray([
            '$ref' => '#/components/schemas/User',
            'summary' => 'User ref',
            'description' => 'User description',
        ]);

        self::assertSame('#/components/schemas/User', $schema->ref);
        self::assertSame('User ref', $schema->refSummary);
        self::assertSame('User description', $schema->refDescription);
    }

    #[Test]
    public function dollar_schema_maps_to_json_schema_dialect(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(['$schema' => 'https://example.com/dialect']);

        self::assertSame('https://example.com/dialect', $schema->jsonSchemaDialect);
    }

    #[Test]
    public function version_3_0_exclusive_minimum_bool_uses_minimum_value(): void
    {
        $converter = new SchemaFromArrayConverter('3.0.0');

        $schema = $converter->fromArray([
            'type' => 'integer',
            'minimum' => 5,
            'exclusiveMinimum' => true,
        ]);

        self::assertSame(5.0, $schema->exclusiveMinimum);
    }

    #[Test]
    public function version_3_2_exclusive_minimum_takes_numeric_value(): void
    {
        $converter = new SchemaFromArrayConverter('3.2.0');

        $schema = $converter->fromArray([
            'type' => 'integer',
            'exclusiveMinimum' => 3,
        ]);

        self::assertSame(3.0, $schema->exclusiveMinimum);
    }

    #[Test]
    public function version_3_2_nullable_extends_type_with_null(): void
    {
        $converter = new SchemaFromArrayConverter('3.2.0');

        $schema = $converter->fromArray(['type' => 'string', 'nullable' => true]);

        self::assertSame(['string', 'null'], $schema->type);
    }

    #[Test]
    public function version_3_0_nullable_keeps_type_unchanged(): void
    {
        $converter = new SchemaFromArrayConverter('3.0.0');

        $schema = $converter->fromArray(['type' => 'string', 'nullable' => true]);

        self::assertSame('string', $schema->type);
    }

    #[Test]
    public function round_trip_parse_and_serialize_preserves_data(): void
    {
        $converter = new SchemaFromArrayConverter('3.2.0');
        $original = [
            'type' => 'object',
            'title' => 'User',
            'description' => 'A user record',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string', 'minLength' => 1],
            ],
            'required' => ['id'],
            'additionalProperties' => false,
        ];

        $schema = $converter->fromArray($original);
        $serialized = $schema->jsonSerialize();

        self::assertSame($original['type'], $serialized['type']);
        self::assertSame($original['title'], $serialized['title']);
        self::assertSame($original['description'], $serialized['description']);
        self::assertSame($original['required'], $serialized['required']);
        self::assertFalse($serialized['additionalProperties']);
        self::assertArrayHasKey('id', $serialized['properties']);
        self::assertSame('integer', $serialized['properties']['id']->type);
    }

    #[Test]
    public function additional_properties_as_boolean_passes_through(): void
    {
        $converter = new SchemaFromArrayConverter();

        $schema = $converter->fromArray(['type' => 'object', 'additionalProperties' => true]);

        self::assertTrue($schema->additionalProperties);

        $schemaFalse = $converter->fromArray(['type' => 'object', 'additionalProperties' => false]);

        self::assertFalse($schemaFalse->additionalProperties);
    }

    #[Test]
    public function composition_keywords_parsed_as_schema_lists(): void
    {
        $converter = new SchemaFromArrayConverter();
        $data = [
            'allOf' => [['type' => 'object']],
            'anyOf' => [['type' => 'string'], ['type' => 'integer']],
            'oneOf' => [['type' => 'string']],
        ];

        $schema = $converter->fromArray($data);

        self::assertCount(1, $schema->allOf);
        self::assertCount(2, $schema->anyOf);
        self::assertCount(1, $schema->oneOf);
        self::assertSame('object', $schema->allOf[0]->type);
    }
}
