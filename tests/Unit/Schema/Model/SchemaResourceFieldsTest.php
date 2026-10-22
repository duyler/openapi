<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Internal\ArrayFields;
use Duyler\OpenApi\Schema\Model\Internal\CompositionFields;
use Duyler\OpenApi\Schema\Model\Internal\ObjectFields;
use Duyler\OpenApi\Schema\Model\Internal\ScalarFields;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Parser\SchemaFromArrayConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

#[CoversClass(Schema::class)]
final class SchemaResourceFieldsTest extends TestCase
{
    private SchemaFromArrayConverter $parser;

    protected function setUp(): void
    {
        $this->parser = new SchemaFromArrayConverter();
    }

    #[Test]
    public function round_trip_preserves_resource_fields(): void
    {
        $schema = new Schema(
            id: 'https://example.com/root',
            anchor: 'nameAnchor',
            dynamicAnchor: 'node',
            dynamicRef: '#node',
            comment: 'resource comment',
            vocabulary: [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
                'https://example.com/vocab/custom' => false,
            ],
            defs: [
                'named' => new Schema(
                    type: 'object',
                    properties: ['name' => new Schema(type: 'string')],
                    required: ['name'],
                ),
                'trueSchema' => new Schema(),
                'falseSchema' => new Schema(not: new Schema()),
            ],
        );

        $wire = $schema->jsonSerialize();
        $json = json_encode($wire, JSON_THROW_ON_ERROR);
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        $reparsed = $this->parser->fromArray($decoded);

        self::assertSame('https://example.com/root', $reparsed->id);
        self::assertSame('nameAnchor', $reparsed->anchor);
        self::assertSame('node', $reparsed->dynamicAnchor);
        self::assertSame('#node', $reparsed->dynamicRef);
        self::assertSame('resource comment', $reparsed->comment);
        self::assertSame(
            [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
                'https://example.com/vocab/custom' => false,
            ],
            $reparsed->vocabulary,
        );
        self::assertNotNull($reparsed->defs);
        self::assertSame(['named', 'trueSchema', 'falseSchema'], array_keys($reparsed->defs));
        self::assertEquals($schema->defs['named'], $reparsed->defs['named']);
        self::assertEquals(new Schema(), $reparsed->defs['trueSchema']);
        self::assertEquals(new Schema(not: new Schema()), $reparsed->defs['falseSchema']);
        self::assertEquals($schema, $reparsed);
    }

    #[Test]
    public function with_override_groups_preserves_resource_fields(): void
    {
        $schema = new Schema(
            type: 'string',
            description: 'original description',
            id: 'https://example.com/root',
            anchor: 'nameAnchor',
            dynamicAnchor: 'node',
            dynamicRef: '#node',
            comment: 'resource comment',
            vocabulary: ['https://json-schema.org/draft/2020-12/vocab/core' => true],
            defs: ['named' => new Schema(type: 'object')],
        );

        $overridden = $schema->withOverrideGroups(
            scalar: new ScalarFields(description: 'overridden description'),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: true,
        );

        self::assertSame('overridden description', $overridden->description);
        self::assertTrue($overridden->nullable);
        self::assertSame('https://example.com/root', $overridden->id);
        self::assertSame('nameAnchor', $overridden->anchor);
        self::assertSame('node', $overridden->dynamicAnchor);
        self::assertSame('#node', $overridden->dynamicRef);
        self::assertSame('resource comment', $overridden->comment);
        self::assertSame(
            ['https://json-schema.org/draft/2020-12/vocab/core' => true],
            $overridden->vocabulary,
        );
        self::assertEquals(['named' => new Schema(type: 'object')], $overridden->defs);
    }

    #[Test]
    public function from_constraint_groups_defaults_resource_fields_to_null(): void
    {
        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertNull($schema->id);
        self::assertNull($schema->anchor);
        self::assertNull($schema->dynamicAnchor);
        self::assertNull($schema->dynamicRef);
        self::assertNull($schema->comment);
        self::assertNull($schema->vocabulary);
        self::assertNull($schema->defs);
    }

    #[Test]
    public function parse_maps_dollar_wire_keywords_to_resource_fields(): void
    {
        $schema = $this->parser->fromArray([
            '$id' => 'https://example.com/root',
            '$anchor' => 'nameAnchor',
            '$dynamicAnchor' => 'node',
            '$dynamicRef' => '#node',
            '$comment' => 'resource comment',
            '$vocabulary' => [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
                'https://example.com/vocab/custom' => false,
            ],
        ]);

        self::assertSame('https://example.com/root', $schema->id);
        self::assertSame('nameAnchor', $schema->anchor);
        self::assertSame('node', $schema->dynamicAnchor);
        self::assertSame('#node', $schema->dynamicRef);
        self::assertSame('resource comment', $schema->comment);
        self::assertSame(
            [
                'https://json-schema.org/draft/2020-12/vocab/core' => true,
                'https://example.com/vocab/custom' => false,
            ],
            $schema->vocabulary,
        );
    }

    #[Test]
    public function parse_defs_supports_boolean_sub_schemas(): void
    {
        $schema = $this->parser->fromArray([
            '$defs' => [
                'trueSchema' => true,
                'falseSchema' => false,
                'objectSchema' => ['type' => 'string'],
            ],
        ]);

        self::assertNotNull($schema->defs);
        self::assertSame(['trueSchema', 'falseSchema', 'objectSchema'], array_keys($schema->defs));
        self::assertEquals(new Schema(), $schema->defs['trueSchema']);
        self::assertNotNull($schema->defs['falseSchema']->not);
        self::assertEquals(new Schema(), $schema->defs['falseSchema']->not);
        self::assertSame('string', $schema->defs['objectSchema']->type);
    }

    #[Test]
    public function wire_serialization_restores_dollar_names_and_omits_nulls(): void
    {
        $schema = new Schema(
            type: 'string',
            id: 'https://example.com/root',
            comment: 'resource comment',
        );

        $wire = $schema->jsonSerialize();

        self::assertArrayHasKey('$id', $wire);
        self::assertSame('https://example.com/root', $wire['$id']);
        self::assertArrayHasKey('$comment', $wire);
        self::assertSame('resource comment', $wire['$comment']);
        self::assertArrayNotHasKey('$anchor', $wire);
        self::assertArrayNotHasKey('$dynamicAnchor', $wire);
        self::assertArrayNotHasKey('$dynamicRef', $wire);
        self::assertArrayNotHasKey('$vocabulary', $wire);
        self::assertArrayNotHasKey('$defs', $wire);
    }

    #[Test]
    public function empty_schema_serializes_without_resource_keys(): void
    {
        $wire = new Schema()->jsonSerialize();

        self::assertSame([], $wire);
    }
}
