<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Internal\ArrayFields;
use Duyler\OpenApi\Schema\Model\Internal\CompositionFields;
use Duyler\OpenApi\Schema\Model\Internal\ObjectFields;
use Duyler\OpenApi\Schema\Model\Internal\ScalarFields;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaFieldMetadata;
use Duyler\OpenApi\Schema\Parser\Internal\ExtrasSchemaKeywordParser;
use Duyler\OpenApi\Schema\Parser\Internal\KnownSchemaKeywords;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use Duyler\OpenApi\Schema\Serializer\SchemaToArrayConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WeakMap;

use function array_keys;
use function count;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Schema::class)]
#[CoversClass(SchemaToArrayConverter::class)]
#[CoversClass(SchemaFromArrayConverter::class)]
#[CoversClass(ExtrasSchemaKeywordParser::class)]
#[CoversClass(KnownSchemaKeywords::class)]
final class SchemaExtrasRoundTripTest extends TestCase
{
    private SchemaFromArrayConverter $parser;

    private SchemaToArrayConverter $serializer;

    protected function setUp(): void
    {
        $this->parser = new SchemaFromArrayConverter();
        $this->serializer = new SchemaToArrayConverter();
    }

    #[Test]
    public function round_trip_preserves_unknown_keywords_verbatim(): void
    {
        $extras = [
            'x-vendor' => [
                'nested' => ['deep' => true, 'list' => [1, 'two', ['three']]],
                'flag' => false,
                'count' => 3,
                'ratio' => 1.5,
            ],
            'requird' => 'typo',
            '$recursiveRef' => '#/x',
            'x-empty-object' => [],
            'x-null' => null,
        ];

        $schema = new Schema(type: 'object', extras: $extras);

        $wire = $this->decode($schema->jsonSerialize());
        $reparsed = $this->parser->fromArray($wire);
        $rewire = $this->decode($reparsed->jsonSerialize());
        $rereparsed = $this->parser->fromArray($rewire);

        self::assertNotNull($reparsed->extras);
        self::assertSame($extras['x-vendor'], $reparsed->extras['x-vendor']);
        self::assertSame('typo', $reparsed->extras['requird']);
        self::assertSame('#/x', $reparsed->extras['$recursiveRef']);
        self::assertSame([], $reparsed->extras['x-empty-object']);
        self::assertNull($reparsed->extras['x-null']);
        self::assertSame($extras, $reparsed->extras);
        self::assertSame($extras, $rereparsed->extras);
        self::assertSame($wire, $rewire);
    }

    #[Test]
    public function parse_collects_unknown_keywords_and_skips_known_ones(): void
    {
        $schema = $this->parser->fromArray([
            'type' => 'string',
            '$id' => 'https://example.com/root',
            '$defs' => ['base' => ['type' => 'object']],
            'properties' => ['name' => ['type' => 'string']],
            'title' => 'Known title',
            'x-vendor' => ['see' => 'round-trip'],
            'requird' => 'typo',
            '$recursiveRef' => '#/x',
        ]);

        self::assertSame([
            'x-vendor' => ['see' => 'round-trip'],
            'requird' => 'typo',
            '$recursiveRef' => '#/x',
        ], $schema->extras);

        self::assertNotNull($schema->extras);
        self::assertArrayNotHasKey('$id', $schema->extras);
        self::assertArrayNotHasKey('$defs', $schema->extras);
        self::assertArrayNotHasKey('type', $schema->extras);
        self::assertArrayNotHasKey('properties', $schema->extras);
        self::assertArrayNotHasKey('title', $schema->extras);
        self::assertSame('https://example.com/root', $schema->id);
        self::assertSame('string', $schema->type);
    }

    #[Test]
    public function wire_emits_extras_after_known_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            title: 'Known title',
            extras: ['x-vendor' => ['a' => 1], '$recursiveRef' => '#/x'],
        );

        $wire = $schema->jsonSerialize();

        self::assertSame(['title', 'type', 'x-vendor', '$recursiveRef'], array_keys($wire));
    }

    #[Test]
    public function wire_omits_extras_when_null_or_empty(): void
    {
        self::assertSame(['type' => 'string'], new Schema(type: 'string')->jsonSerialize());
        self::assertSame(['type' => 'string'], new Schema(type: 'string', extras: [])->jsonSerialize());
        self::assertSame([], new Schema()->jsonSerialize());
    }

    #[Test]
    public function snapshot_includes_extras_as_last_key_when_present(): void
    {
        $schema = new Schema(type: 'string', extras: ['x-vendor' => ['a' => 1]]);

        $snapshot = $this->serializer->toSnapshotArray($schema, new WeakMap());

        self::assertSame(['x-vendor' => ['a' => 1]], $snapshot['extras']);
        self::assertSame('extras', array_keys($snapshot)[count($snapshot) - 1]);
    }

    #[Test]
    public function snapshot_omits_extras_when_absent(): void
    {
        $snapshot = $this->serializer->toSnapshotArray(new Schema(type: 'string'), new WeakMap());

        self::assertArrayNotHasKey('extras', $snapshot);
    }

    #[Test]
    public function parse_stringifies_non_string_unknown_keys(): void
    {
        $schema = $this->parser->fromArray([123 => 'numeric-key-value']);

        self::assertSame(['123' => 'numeric-key-value'], $schema->extras);
    }

    #[Test]
    public function collector_returns_null_extras_for_known_only_data(): void
    {
        self::assertNull(new ExtrasSchemaKeywordParser()->build(['type' => 'string'])['extras']);
        self::assertNull(new ExtrasSchemaKeywordParser()->build([])['extras']);
        self::assertSame(['x-y' => 1], new ExtrasSchemaKeywordParser()->build(['x-y' => 1])['extras']);
    }

    #[Test]
    public function with_override_groups_preserves_extras(): void
    {
        $schema = new Schema(type: 'string', extras: ['x-vendor' => true]);

        $overridden = $schema->withOverrideGroups(
            scalar: new ScalarFields(description: 'overridden'),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame(['x-vendor' => true], $overridden->extras);
    }

    #[Test]
    public function from_constraint_groups_defaults_extras_to_null(): void
    {
        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertNull($schema->extras);
    }

    #[Test]
    public function every_schema_field_metadata_keyword_is_known(): void
    {
        foreach (SchemaFieldMetadata::fields() as $field) {
            self::assertTrue(KnownSchemaKeywords::isKnown($field->openApiName), $field->openApiName);
        }
    }

    #[Test]
    public function unknown_keywords_are_not_known(): void
    {
        self::assertFalse(KnownSchemaKeywords::isKnown('x-vendor'));
        self::assertFalse(KnownSchemaKeywords::isKnown('requird'));
        self::assertFalse(KnownSchemaKeywords::isKnown('$recursiveRef'));
    }

    /** @return array<string, mixed> */
    private function decode(array $wire): array
    {
        return json_decode(json_encode($wire, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
