<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Serializer\Internal;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaFieldMetadata;
use Duyler\OpenApi\Schema\Model\Xml;
use Duyler\OpenApi\Schema\Serializer\Internal\ArraySchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\CompositionSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\ObjectSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\ScalarSchemaSerializer;
use Duyler\OpenApi\Schema\Serializer\Internal\SnapshotContext;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use LogicException;
use WeakMap;

#[CoversClass(ScalarSchemaSerializer::class)]
#[CoversClass(ArraySchemaSerializer::class)]
#[CoversClass(ObjectSchemaSerializer::class)]
#[CoversClass(CompositionSchemaSerializer::class)]
final class SchemaSerializerCollaboratorsTest extends TestCase
{
    #[Test]
    public function scalar_serializer_extract_wire_returns_property_value(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(type: 'string', format: 'email', title: 'User');

        self::assertSame('string', $serializer->extractWire($schema, 'type'));
        self::assertSame('email', $serializer->extractWire($schema, 'format'));
        self::assertSame('User', $serializer->extractWire($schema, 'title'));
    }

    #[Test]
    public function scalar_serializer_extract_sentinel_uses_has_default_flag(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(hasDefault: true, default: null);

        $sentinel = $serializer->extractSentinel($schema, self::fieldByName('default'));

        self::assertNotNull($sentinel);
        self::assertNull($sentinel->value);
    }

    #[Test]
    public function scalar_serializer_extract_sentinel_wraps_const_value_when_flag_set(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(hasConst: true, const: 'fixed');

        $sentinel = $serializer->extractSentinel($schema, self::fieldByName('const'));

        self::assertNotNull($sentinel);
        self::assertSame('fixed', $sentinel->value);
    }

    #[Test]
    public function scalar_serializer_extract_sentinel_returns_null_when_flag_absent(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(default: 'orphan');

        self::assertNull($serializer->extractSentinel($schema, self::fieldByName('default')));
    }

    #[Test]
    public function scalar_serializer_extract_sentinel_returns_null_for_non_sentinel_field(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(title: 'User');

        self::assertNull($serializer->extractSentinel($schema, self::fieldByName('title')));
    }

    #[Test]
    public function scalar_serializer_extract_snapshot_discriminator_to_array(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(discriminator: new Discriminator(propertyName: 'type', mapping: ['a' => '#/a']));

        $field = self::fieldByName('discriminator');
        $result = $serializer->extractSnapshot($schema, $field);

        self::assertSame(
            ['propertyName' => 'type', 'mapping' => ['a' => '#/a'], 'defaultMapping' => null],
            $result,
        );
    }

    #[Test]
    public function scalar_serializer_extract_snapshot_xml_to_array(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(xml: new Xml(name: 'user', namespace: 'urn:example'));

        $field = self::fieldByName('xml');
        $result = $serializer->extractSnapshot($schema, $field);

        self::assertSame(
            ['name' => 'user', 'namespace' => 'urn:example', 'prefix' => null, 'attribute' => null, 'wrapped' => null, 'nodeType' => null],
            $result,
        );
    }

    #[Test]
    public function scalar_serializer_extract_snapshot_returns_null_for_default_without_flag(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(default: 'orphan');

        self::assertNull($serializer->extractSnapshot($schema, self::fieldByName('default')));
    }

    #[Test]
    public function scalar_serializer_extract_snapshot_returns_null_for_const_without_flag(): void
    {
        $serializer = new ScalarSchemaSerializer();
        $schema = new Schema(const: 'orphan');

        self::assertNull($serializer->extractSnapshot($schema, self::fieldByName('const')));
    }

    #[Test]
    public function array_serializer_extract_snapshot_recurses_into_items(): void
    {
        $serializer = new ArraySchemaSerializer();
        $nested = new Schema(type: 'string');
        $schema = new Schema(items: $nested);

        $context = new SnapshotContext(
            new WeakMap(),
            static fn(Schema $s, WeakMap $v): array => new SchemaToArrayConverter()->toSnapshotArray($s, $v),
        );

        $field = self::fieldByName('items');
        $result = $serializer->extractSnapshot($schema, $field, $context);

        self::assertIsArray($result);
        self::assertSame('string', $result['type']);
    }

    #[Test]
    public function array_serializer_extract_snapshot_returns_bool_for_items_bool(): void
    {
        $serializer = new ArraySchemaSerializer();
        $schema = new Schema(items: false);

        $result = $serializer->extractSnapshot(
            $schema,
            self::fieldByName('items'),
            new SnapshotContext(new WeakMap(), static fn(): array => []),
        );

        self::assertFalse($result);
    }

    #[Test]
    public function array_serializer_extract_snapshot_prefix_items_recurses(): void
    {
        $serializer = new ArraySchemaSerializer();
        $schema = new Schema(prefixItems: [new Schema(type: 'integer'), new Schema(type: 'string')]);

        $context = new SnapshotContext(
            new WeakMap(),
            static fn(Schema $s, WeakMap $v): array => ['type' => $s->type],
        );
        $result = $serializer->extractSnapshot($schema, self::fieldByName('prefixItems'), $context);

        self::assertIsArray($result);
        self::assertCount(2, $result);
        self::assertSame('integer', $result[0]['type']);
        self::assertSame('string', $result[1]['type']);
    }

    #[Test]
    public function object_serializer_extract_snapshot_returns_bool_for_additional_properties(): void
    {
        $serializer = new ObjectSchemaSerializer();
        $schema = new Schema(additionalProperties: true);

        $result = $serializer->extractSnapshot(
            $schema,
            self::fieldByName('additionalProperties'),
            new SnapshotContext(new WeakMap(), static fn(): array => []),
        );

        self::assertTrue($result);
    }

    #[Test]
    public function object_serializer_extract_snapshot_recurses_into_properties(): void
    {
        $serializer = new ObjectSchemaSerializer();
        $nested = new Schema(type: 'integer');
        $schema = new Schema(properties: ['id' => $nested]);

        $context = new SnapshotContext(
            new WeakMap(),
            static fn(Schema $s, WeakMap $v): array => ['type' => $s->type],
        );
        $result = $serializer->extractSnapshot($schema, self::fieldByName('properties'), $context);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('integer', $result['id']['type']);
    }

    #[Test]
    public function composition_serializer_extract_snapshot_returns_bool_for_if(): void
    {
        $serializer = new CompositionSchemaSerializer();
        $schema = new Schema(if: false, then: true, else: false);

        $context = new SnapshotContext(new WeakMap(), static fn(Schema $s, WeakMap $v): array => []);

        self::assertFalse($serializer->extractSnapshot($schema, self::fieldByName('if'), $context));
        self::assertTrue($serializer->extractSnapshot($schema, self::fieldByName('then'), $context));
        self::assertFalse($serializer->extractSnapshot($schema, self::fieldByName('else'), $context));
    }

    #[Test]
    public function composition_serializer_extract_snapshot_all_of_recurses(): void
    {
        $serializer = new CompositionSchemaSerializer();
        $schema = new Schema(allOf: [new Schema(type: 'object')]);

        $context = new SnapshotContext(
            new WeakMap(),
            static fn(Schema $s, WeakMap $v): array => ['type' => $s->type],
        );
        $result = $serializer->extractSnapshot($schema, self::fieldByName('allOf'), $context);

        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertSame('object', $result[0]['type']);
    }

    private static function fieldByName(string $name): FieldMetadata
    {
        foreach (SchemaFieldMetadata::fields() as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        throw new LogicException("Field $name not found");
    }
}
