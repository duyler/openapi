<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\ArrayConstraints;
use Duyler\OpenApi\Schema\Model\CompositionConstraints;
use Duyler\OpenApi\Schema\Model\NumericConstraints;
use Duyler\OpenApi\Schema\Model\ObjectConstraints;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\StringConstraints;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Schema::class)]
#[CoversClass(StringConstraints::class)]
#[CoversClass(NumericConstraints::class)]
#[CoversClass(ArrayConstraints::class)]
#[CoversClass(ObjectConstraints::class)]
#[CoversClass(CompositionConstraints::class)]
final class SchemaSubDtoTest extends TestCase
{
    #[Test]
    public function string_constraints_grouped_correctly(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 3,
            maxLength: 50,
            pattern: '^[a-z]+$',
        );

        $constraints = $schema->stringConstraints();

        self::assertNotNull($constraints);
        self::assertSame(3, $constraints->minLength);
        self::assertSame(50, $constraints->maxLength);
        self::assertSame('^[a-z]+$', $constraints->pattern);
    }

    #[Test]
    public function string_constraints_returns_null_when_no_string_fields(): void
    {
        $schema = new Schema(type: 'integer');

        self::assertNull($schema->stringConstraints());
    }

    #[Test]
    public function numeric_constraints_grouped_correctly(): void
    {
        $schema = new Schema(
            type: 'integer',
            multipleOf: 5.0,
            minimum: 0.0,
            maximum: 100.0,
            exclusiveMinimum: -1.0,
            exclusiveMaximum: 101.0,
        );

        $constraints = $schema->numericConstraints();

        self::assertNotNull($constraints);
        self::assertSame(5.0, $constraints->multipleOf);
        self::assertSame(0.0, $constraints->minimum);
        self::assertSame(100.0, $constraints->maximum);
        self::assertSame(-1.0, $constraints->exclusiveMinimum);
        self::assertSame(101.0, $constraints->exclusiveMaximum);
    }

    #[Test]
    public function numeric_constraints_returns_null_when_no_numeric_fields(): void
    {
        $schema = new Schema(type: 'string');

        self::assertNull($schema->numericConstraints());
    }

    #[Test]
    public function array_constraints_grouped_correctly(): void
    {
        $items = new Schema(type: 'integer');
        $contains = new Schema(type: 'string');

        $schema = new Schema(
            type: 'array',
            items: $items,
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
            contains: $contains,
            minContains: 1,
            maxContains: 3,
        );

        $constraints = $schema->arrayConstraints();

        self::assertNotNull($constraints);
        self::assertSame($items, $constraints->items);
        self::assertSame(1, $constraints->minItems);
        self::assertSame(10, $constraints->maxItems);
        self::assertTrue($constraints->uniqueItems);
        self::assertSame($contains, $constraints->contains);
        self::assertSame(1, $constraints->minContains);
        self::assertSame(3, $constraints->maxContains);
    }

    #[Test]
    public function array_constraints_returns_null_when_no_array_fields(): void
    {
        $schema = new Schema(type: 'string');

        self::assertNull($schema->arrayConstraints());
    }

    #[Test]
    public function object_constraints_grouped_correctly(): void
    {
        $properties = ['name' => new Schema(type: 'string')];
        $additional = new Schema(type: 'string');

        $schema = new Schema(
            type: 'object',
            properties: $properties,
            required: ['name'],
            minProperties: 1,
            maxProperties: 5,
            additionalProperties: $additional,
        );

        $constraints = $schema->objectConstraints();

        self::assertNotNull($constraints);
        self::assertSame($properties, $constraints->properties);
        self::assertSame(['name'], $constraints->required);
        self::assertSame(1, $constraints->minProperties);
        self::assertSame(5, $constraints->maxProperties);
        self::assertSame($additional, $constraints->additionalProperties);
    }

    #[Test]
    public function object_constraints_returns_null_when_no_object_fields(): void
    {
        $schema = new Schema(type: 'string');

        self::assertNull($schema->objectConstraints());
    }

    #[Test]
    public function composition_constraints_grouped_correctly(): void
    {
        $allOf = [new Schema(type: 'object')];
        $not = new Schema(type: 'string');

        $schema = new Schema(
            allOf: $allOf,
            not: $not,
        );

        $constraints = $schema->compositionConstraints();

        self::assertNotNull($constraints);
        self::assertSame($allOf, $constraints->allOf);
        self::assertSame($not, $constraints->not);
    }

    #[Test]
    public function composition_constraints_returns_null_when_no_composition_fields(): void
    {
        $schema = new Schema(type: 'string');

        self::assertNull($schema->compositionConstraints());
    }

    #[Test]
    public function sub_dto_accessor_round_trips_through_facade_properties(): void
    {
        $schema = new Schema(
            type: 'string',
            minLength: 2,
            minimum: 1.0,
            minItems: 1,
            minProperties: 1,
            allOf: [new Schema(type: 'object')],
        );

        self::assertSame(2, $schema->stringConstraints()?->minLength);
        self::assertSame(1.0, $schema->numericConstraints()?->minimum);
        self::assertSame(1, $schema->arrayConstraints()?->minItems);
        self::assertSame(1, $schema->objectConstraints()?->minProperties);
        self::assertNotNull($schema->compositionConstraints()?->allOf);
    }

    #[Test]
    public function sub_dto_classes_are_empty_when_constructed_with_defaults(): void
    {
        self::assertTrue(new StringConstraints()->isEmpty());
        self::assertTrue(new NumericConstraints()->isEmpty());
        self::assertTrue(new ArrayConstraints()->isEmpty());
        self::assertTrue(new ObjectConstraints()->isEmpty());
        self::assertTrue(new CompositionConstraints()->isEmpty());
    }

    #[Test]
    public function sub_dto_classes_are_not_empty_when_any_field_set(): void
    {
        self::assertFalse(new StringConstraints(minLength: 1)->isEmpty());
        self::assertFalse(new NumericConstraints(minimum: 1.0)->isEmpty());
        self::assertFalse(new ArrayConstraints(minItems: 1)->isEmpty());
        self::assertFalse(new ObjectConstraints(minProperties: 1)->isEmpty());
        self::assertFalse(new CompositionConstraints(not: new Schema())->isEmpty());
    }
}
