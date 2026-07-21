<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Serializer;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use WeakMap;

use const JSON_THROW_ON_ERROR;

#[CoversClass(SchemaToArrayConverter::class)]
final class SchemaToArrayConverterTest extends TestCase
{
    #[Test]
    public function ref_short_circuit_emits_only_ref_summary_description(): void
    {
        $schema = new Schema(
            ref: '#/components/schemas/User',
            refSummary: 'User schema',
            refDescription: 'User reference',
            title: 'Ignored',
        );

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertSame(
            [
                '$ref' => '#/components/schemas/User',
                'summary' => 'User schema',
                'description' => 'User reference',
            ],
            $result,
        );
    }

    #[Test]
    public function null_fields_are_omitted_in_wire_form(): void
    {
        $schema = new Schema(type: 'string', format: 'email');

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertSame(['type' => 'string', 'format' => 'email'], $result);
    }

    #[Test]
    public function boolean_fields_emitted_only_when_truthy(): void
    {
        $schema = new Schema(
            type: 'string',
            deprecated: false,
            readOnly: true,
            writeOnly: false,
            nullable: true,
        );

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayNotHasKey('deprecated', $result);
        self::assertArrayNotHasKey('writeOnly', $result);
        self::assertTrue($result['readOnly']);
        self::assertTrue($result['nullable']);
    }

    #[Test]
    public function default_emitted_via_has_default_sentinel(): void
    {
        $schemaWithNullDefault = new Schema(hasDefault: true, default: null);

        $result = new SchemaToArrayConverter()->toWireArray($schemaWithNullDefault);

        self::assertArrayHasKey('default', $result);
        self::assertNull($result['default']);
    }

    #[Test]
    public function const_emitted_via_has_const_sentinel(): void
    {
        $schemaWithNullConst = new Schema(hasConst: true, const: null);

        $result = new SchemaToArrayConverter()->toWireArray($schemaWithNullConst);

        self::assertArrayHasKey('const', $result);
        self::assertNull($result['const']);
    }

    #[Test]
    public function all_relevant_fields_serialized_correctly(): void
    {
        $nested = new Schema(type: 'string');

        $schema = new Schema(
            title: 'User',
            description: 'A user',
            type: 'object',
            multipleOf: 1.0,
            maximum: 100.0,
            minimum: 0.0,
            minLength: 3,
            maxLength: 50,
            pattern: '^[a-z]+$',
            minItems: 1,
            maxItems: 10,
            minProperties: 1,
            maxProperties: 5,
            required: ['name'],
            allOf: [$nested],
            anyOf: [$nested],
            oneOf: [$nested],
            not: $nested,
            properties: ['name' => $nested],
            additionalProperties: true,
            items: $nested,
            contains: $nested,
            format: 'email',
            enum: ['a', 'b'],
            xml: new Xml(name: 'user'),
            discriminator: new Discriminator(propertyName: 'type'),
        );

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertSame('User', $result['title']);
        self::assertSame('A user', $result['description']);
        self::assertSame('object', $result['type']);
        self::assertSame(1.0, $result['multipleOf']);
        self::assertSame(100.0, $result['maximum']);
        self::assertSame(0.0, $result['minimum']);
        self::assertSame(3, $result['minLength']);
        self::assertSame(50, $result['maxLength']);
        self::assertSame('^[a-z]+$', $result['pattern']);
        self::assertSame(1, $result['minItems']);
        self::assertSame(10, $result['maxItems']);
        self::assertSame(1, $result['minProperties']);
        self::assertSame(5, $result['maxProperties']);
        self::assertSame(['name'], $result['required']);
        self::assertSame([$nested], $result['allOf']);
        self::assertSame([$nested], $result['anyOf']);
        self::assertSame([$nested], $result['oneOf']);
        self::assertSame($nested, $result['not']);
        self::assertSame(['name' => $nested], $result['properties']);
        self::assertTrue($result['additionalProperties']);
        self::assertSame($nested, $result['items']);
        self::assertSame($nested, $result['contains']);
        self::assertSame('email', $result['format']);
        self::assertSame(['a', 'b'], $result['enum']);
        self::assertEquals(new Xml(name: 'user'), $result['xml']);
        self::assertEquals(new Discriminator(propertyName: 'type'), $result['discriminator']);
    }

    #[Test]
    public function json_schema_dialect_maps_to_dollar_schema_key(): void
    {
        $schema = new Schema(jsonSchemaDialect: 'https://example.com/schema');

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('$schema', $result);
        self::assertSame('https://example.com/schema', $result['$schema']);
    }

    #[Test]
    public function snapshot_includes_has_default_and_has_const_flags(): void
    {
        $schema = new Schema(hasDefault: true, default: 42, hasConst: true, const: 'x');

        $result = new SchemaToArrayConverter()->toSnapshotArray($schema, new WeakMap());

        self::assertTrue($result['hasDefault']);
        self::assertSame(42, $result['default']);
        self::assertTrue($result['hasConst']);
        self::assertSame('x', $result['const']);
    }

    #[Test]
    public function snapshot_recurses_into_nested_schemas(): void
    {
        $nested = new Schema(type: 'string');
        $schema = new Schema(items: $nested);

        $result = new SchemaToArrayConverter()->toSnapshotArray($schema, new WeakMap());

        self::assertIsArray($result['items']);
        self::assertSame('string', $result['items']['type']);
    }

    #[Test]
    public function snapshot_detects_cycles_via_weak_map(): void
    {
        $schema = new Schema(type: 'string');
        $parent = new Schema(items: $schema);

        $visited = new WeakMap();
        $visited[$schema] = 0;

        $result = new SchemaToArrayConverter()->toSnapshotArray($schema, $visited);

        self::assertSame(['__circular_ref__' => 0], $result);

        $parentResult = new SchemaToArrayConverter()->toSnapshotArray($parent, new WeakMap());
        self::assertArrayHasKey('type', $parentResult['items']);
    }

    #[Test]
    public function snapshot_flattens_discriminator_and_xml(): void
    {
        $schema = new Schema(
            discriminator: new Discriminator(propertyName: 'type', mapping: ['a' => '#/a']),
            xml: new Xml(name: 'user', namespace: 'urn:example'),
        );

        $result = new SchemaToArrayConverter()->toSnapshotArray($schema, new WeakMap());

        self::assertSame(
            ['propertyName' => 'type', 'mapping' => ['a' => '#/a'], 'defaultMapping' => null],
            $result['discriminator'],
        );
        self::assertSame(
            ['name' => 'user', 'namespace' => 'urn:example', 'prefix' => null, 'attribute' => null, 'wrapped' => null, 'nodeType' => null],
            $result['xml'],
        );
    }

    #[Test]
    public function wire_output_matches_json_serialize(): void
    {
        $schema = new Schema(
            type: 'object',
            title: 'User',
            properties: ['name' => new Schema(type: 'string')],
            required: ['name'],
        );

        $wireResult = new SchemaToArrayConverter()->toWireArray($schema);
        $jsonResult = $schema->jsonSerialize();

        self::assertSame($wireResult, $jsonResult);
    }

    #[Test]
    public function wire_array_round_trips_through_from_array_converter(): void
    {
        $original = new Schema(
            title: 'User',
            description: 'A user record',
            type: 'object',
            default: ['guest'],
            hasDefault: true,
            deprecated: true,
            readOnly: true,
            multipleOf: 1.0,
            maximum: 100.0,
            minimum: 0.0,
            minLength: 3,
            maxLength: 50,
            pattern: '^[a-z]+$',
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
            minProperties: 1,
            maxProperties: 5,
            required: ['name'],
            format: 'email',
            enum: ['a', 'b'],
            const: 'fixed',
            hasConst: true,
            properties: [
                'name' => new Schema(type: 'string', minLength: 1),
            ],
        );

        $converter = new SchemaToArrayConverter();
        $parser = new SchemaFromArrayConverter(documentVersion: '3.2.0');

        $wire = $converter->toWireArray($original);
        $json = json_encode($wire, JSON_THROW_ON_ERROR);
        $reparsed = $parser->fromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        $rejson = json_encode($converter->toWireArray($reparsed), JSON_THROW_ON_ERROR);

        self::assertSame($json, $rejson);
    }

    #[Test]
    public function round_trip_items_false_emits_false(): void
    {
        $schema = new Schema(type: 'array', items: false);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('items', $result);
        self::assertFalse($result['items']);
    }

    #[Test]
    public function round_trip_items_true_emits_true(): void
    {
        $schema = new Schema(type: 'array', items: true);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('items', $result);
        self::assertTrue($result['items']);
    }

    #[Test]
    public function round_trip_contains_true_emits_true(): void
    {
        $schema = new Schema(contains: true);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('contains', $result);
        self::assertTrue($result['contains']);
    }

    #[Test]
    public function round_trip_not_false_emits_false(): void
    {
        $schema = new Schema(not: false);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('not', $result);
        self::assertFalse($result['not']);
    }

    #[Test]
    public function round_trip_if_false_then_true_else_false_emits_bool(): void
    {
        $schema = new Schema(if: false, then: true, else: false);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('if', $result);
        self::assertFalse($result['if']);
        self::assertArrayHasKey('then', $result);
        self::assertTrue($result['then']);
        self::assertArrayHasKey('else', $result);
        self::assertFalse($result['else']);
    }

    #[Test]
    public function round_trip_property_names_false_emits_false(): void
    {
        $schema = new Schema(propertyNames: false);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('propertyNames', $result);
        self::assertFalse($result['propertyNames']);
    }

    #[Test]
    public function round_trip_unevaluated_items_false_emits_false(): void
    {
        $schema = new Schema(unevaluatedItems: false);

        $result = new SchemaToArrayConverter()->toWireArray($schema);

        self::assertArrayHasKey('unevaluatedItems', $result);
        self::assertFalse($result['unevaluatedItems']);
    }

    #[Test]
    public function round_trip_items_false_passes_back_through_from_array(): void
    {
        $parser = new SchemaFromArrayConverter(documentVersion: '3.2.0');
        $serializer = new SchemaToArrayConverter();

        $schema = new Schema(type: 'array', items: false);
        $wire = $serializer->toWireArray($schema);
        $reparsed = $parser->fromArray($wire);

        self::assertFalse($reparsed->items);
    }

    #[Test]
    public function snapshot_items_false_emits_false(): void
    {
        $schema = new Schema(items: false);

        $result = new SchemaToArrayConverter()->toSnapshotArray($schema, new WeakMap());

        self::assertFalse($result['items']);
    }

    #[Test]
    public function snapshot_not_true_emits_true(): void
    {
        $schema = new Schema(not: true);

        $result = new SchemaToArrayConverter()->toSnapshotArray($schema, new WeakMap());

        self::assertTrue($result['not']);
    }
}
