<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Schema\Internal\SchemaSubschemaWalker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
final class SchemaSubschemaWalkerTest extends TestCase
{
    private SchemaSubschemaWalker $walker;

    protected function setUp(): void
    {
        $this->walker = new SchemaSubschemaWalker();
    }

    #[Test]
    public function iterate_sub_schemas_yields_relative_pointer_segments_as_keys(): void
    {
        $property = new Schema(type: 'string');
        $prefixItem = new Schema(type: 'integer');
        $allOfItem = new Schema(type: 'object');
        $anyOfItem = new Schema(type: 'number');
        $oneOfItem = new Schema(type: 'boolean');
        $patternProperty = new Schema(type: 'string');
        $dependentSchema = new Schema(type: 'object');
        $defSchema = new Schema(type: 'string');
        $items = new Schema(type: 'string');
        $not = new Schema(type: 'null');
        $contains = new Schema(type: 'string');
        $propertyNames = new Schema(type: 'string');
        $if = new Schema(type: 'object');
        $then = new Schema(type: 'object');
        $else = new Schema(type: 'object');
        $unevaluatedItems = new Schema(type: 'string');
        $additionalProperties = new Schema(type: 'string');
        $unevaluatedProperties = new Schema(type: 'string');
        $contentSchema = new Schema(type: 'string');
        $schema = new Schema(
            properties: ['name' => $property],
            prefixItems: [$prefixItem],
            allOf: [$allOfItem],
            anyOf: [$anyOfItem],
            oneOf: [$oneOfItem],
            patternProperties: ['^s' => $patternProperty],
            dependentSchemas: ['credit' => $dependentSchema],
            defs: ['Def' => $defSchema],
            items: $items,
            not: $not,
            contains: $contains,
            propertyNames: $propertyNames,
            if: $if,
            then: $then,
            else: $else,
            unevaluatedItems: $unevaluatedItems,
            additionalProperties: $additionalProperties,
            unevaluatedProperties: $unevaluatedProperties,
            contentSchema: $contentSchema,
        );

        $yielded = $this->collectWithKeys($schema);

        self::assertSame([
            'properties/name' => $property,
            'prefixItems/0' => $prefixItem,
            'allOf/0' => $allOfItem,
            'anyOf/0' => $anyOfItem,
            'oneOf/0' => $oneOfItem,
            'patternProperties/^s' => $patternProperty,
            'dependentSchemas/credit' => $dependentSchema,
            'defs/Def' => $defSchema,
            'items' => $items,
            'not' => $not,
            'contains' => $contains,
            'propertyNames' => $propertyNames,
            'if' => $if,
            'then' => $then,
            'else' => $else,
            'unevaluatedItems' => $unevaluatedItems,
            'additionalProperties' => $additionalProperties,
            'unevaluatedProperties' => $unevaluatedProperties,
            'contentSchema' => $contentSchema,
        ], $yielded);
    }

    #[Test]
    public function iterate_sub_schemas_escape_map_key_tokens(): void
    {
        $tildeProperty = new Schema(type: 'string');
        $slashProperty = new Schema(type: 'string');
        $mixedProperty = new Schema(type: 'string');
        $slashPatternProperty = new Schema(type: 'string');
        $tildePatternProperty = new Schema(type: 'string');
        $dependentSchema = new Schema(type: 'object');
        $defSchema = new Schema(type: 'string');
        $schema = new Schema(
            properties: ['a~b' => $tildeProperty, 'foo/bar' => $slashProperty, 'a~/b' => $mixedProperty],
            patternProperties: ['^a/b$' => $slashPatternProperty, 'p~q' => $tildePatternProperty],
            dependentSchemas: ['a/b' => $dependentSchema],
            defs: ['a~b' => $defSchema],
        );

        $yielded = $this->collectWithKeys($schema);

        self::assertSame([
            'properties/a~0b' => $tildeProperty,
            'properties/foo~1bar' => $slashProperty,
            'properties/a~0~1b' => $mixedProperty,
            'patternProperties/^a~1b$' => $slashPatternProperty,
            'patternProperties/p~0q' => $tildePatternProperty,
            'dependentSchemas/a~1b' => $dependentSchema,
            'defs/a~0b' => $defSchema,
        ], $yielded);
    }

    #[Test]
    public function iterate_sub_schemas_yields_every_composition_key(): void
    {
        $property = new Schema(type: 'string');
        $prefixItem = new Schema(type: 'integer');
        $allOfItem = new Schema(type: 'object');
        $anyOfItem = new Schema(type: 'number');
        $oneOfItem = new Schema(type: 'boolean');
        $patternProperty = new Schema(type: 'string');
        $dependentSchema = new Schema(type: 'object');
        $schema = new Schema(
            properties: ['name' => $property],
            prefixItems: [$prefixItem],
            allOf: [$allOfItem],
            anyOf: [$anyOfItem],
            oneOf: [$oneOfItem],
            patternProperties: ['^s' => $patternProperty],
            dependentSchemas: ['credit' => $dependentSchema],
        );

        $yielded = $this->collect($schema);

        self::assertCount(7, $yielded);
        self::assertContains($property, $yielded);
        self::assertContains($prefixItem, $yielded);
        self::assertContains($allOfItem, $yielded);
        self::assertContains($anyOfItem, $yielded);
        self::assertContains($oneOfItem, $yielded);
        self::assertContains($patternProperty, $yielded);
        self::assertContains($dependentSchema, $yielded);
    }

    #[Test]
    public function iterate_sub_schemas_yields_defs_schemas(): void
    {
        $defSchema = new Schema(type: 'string');
        $schema = new Schema(defs: ['Def' => $defSchema]);

        $yielded = $this->collect($schema);

        self::assertContains($defSchema, $yielded);
    }

    #[Test]
    public function iterate_sub_schemas_yields_defs_between_dependent_schemas_and_single_keywords(): void
    {
        $dependentSchema = new Schema(type: 'object');
        $defSchema = new Schema(type: 'string');
        $item = new Schema(type: 'integer');
        $schema = new Schema(
            dependentSchemas: ['credit' => $dependentSchema],
            defs: ['Def' => $defSchema],
            items: $item,
        );

        $yielded = $this->collect($schema);

        self::assertSame([$dependentSchema, $defSchema, $item], $yielded);
    }

    #[Test]
    public function iterate_sub_schemas_yields_single_keyword_schemas(): void
    {
        $items = new Schema(type: 'string');
        $not = new Schema(type: 'null');
        $contains = new Schema(type: 'string');
        $propertyNames = new Schema(type: 'string');
        $if = new Schema(type: 'object');
        $then = new Schema(type: 'object');
        $else = new Schema(type: 'object');
        $unevaluatedItems = new Schema(type: 'string');
        $additionalProperties = new Schema(type: 'string');
        $unevaluatedProperties = new Schema(type: 'string');
        $contentSchema = new Schema(type: 'string');
        $schema = new Schema(
            items: $items,
            not: $not,
            contains: $contains,
            propertyNames: $propertyNames,
            if: $if,
            then: $then,
            else: $else,
            unevaluatedItems: $unevaluatedItems,
            additionalProperties: $additionalProperties,
            unevaluatedProperties: $unevaluatedProperties,
            contentSchema: $contentSchema,
        );

        $yielded = $this->collect($schema);

        self::assertCount(11, $yielded);
        self::assertContains($items, $yielded);
        self::assertContains($not, $yielded);
        self::assertContains($contains, $yielded);
        self::assertContains($propertyNames, $yielded);
        self::assertContains($if, $yielded);
        self::assertContains($then, $yielded);
        self::assertContains($else, $yielded);
        self::assertContains($unevaluatedItems, $yielded);
        self::assertContains($additionalProperties, $yielded);
        self::assertContains($unevaluatedProperties, $yielded);
        self::assertContains($contentSchema, $yielded);
    }

    #[Test]
    public function collect_single_sub_schemas_excludes_boolean_and_null_entries(): void
    {
        $schema = new Schema(
            items: new Schema(type: 'string'),
            additionalProperties: true,
            unevaluatedItems: null,
        );

        $collected = $this->walker->collectSingleSubSchemas($schema);

        self::assertCount(1, $collected);
        self::assertInstanceOf(Schema::class, $collected[0]);
    }

    #[Test]
    public function iterate_sub_schemas_yields_nothing_for_leaf_schema(): void
    {
        $yielded = $this->collect(new Schema(type: 'string'));

        self::assertSame([], $yielded);
    }

    /**
     * @return array<string, Schema>
     */
    private function collectWithKeys(Schema $schema): array
    {
        $yielded = [];

        foreach ($this->walker->iterateSubSchemas($schema) as $segment => $subSchema) {
            $yielded[$segment] = $subSchema;
        }

        return $yielded;
    }

    /**
     * @return list<Schema>
     */
    private function collect(Schema $schema): array
    {
        $yielded = [];

        foreach ($this->walker->iterateSubSchemas($schema) as $subSchema) {
            $yielded[] = $subSchema;
        }

        return $yielded;
    }
}
