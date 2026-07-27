<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\Internal\ArrayFields;
use Duyler\OpenApi\Schema\Model\Internal\CompositionFields;
use Duyler\OpenApi\Schema\Model\Internal\ObjectFields;
use Duyler\OpenApi\Schema\Model\Internal\ScalarFields;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\Xml;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScalarFields::class)]
#[CoversClass(ArrayFields::class)]
#[CoversClass(ObjectFields::class)]
#[CoversClass(CompositionFields::class)]
final class ConstraintGroupsTest extends TestCase
{
    #[Test]
    public function scalar_fields_defaults_are_null_or_promoted_to_null(): void
    {
        $fields = new ScalarFields();

        self::assertNull($fields->ref);
        self::assertNull($fields->refSummary);
        self::assertNull($fields->refDescription);
        self::assertNull($fields->format);
        self::assertNull($fields->title);
        self::assertNull($fields->description);
        self::assertNull($fields->default);
        self::assertNull($fields->hasDefault);
        self::assertNull($fields->deprecated);
        self::assertNull($fields->readOnly);
        self::assertNull($fields->writeOnly);
        self::assertNull($fields->type);
        self::assertNull($fields->const);
        self::assertNull($fields->hasConst);
        self::assertNull($fields->multipleOf);
        self::assertNull($fields->maximum);
        self::assertNull($fields->exclusiveMaximum);
        self::assertNull($fields->minimum);
        self::assertNull($fields->exclusiveMinimum);
        self::assertNull($fields->maxLength);
        self::assertNull($fields->minLength);
        self::assertNull($fields->pattern);
        self::assertNull($fields->example);
        self::assertNull($fields->examples);
        self::assertNull($fields->enum);
        self::assertNull($fields->contentEncoding);
        self::assertNull($fields->contentMediaType);
        self::assertNull($fields->contentSchema);
        self::assertNull($fields->jsonSchemaDialect);
        self::assertNull($fields->discriminator);
        self::assertNull($fields->xml);
    }

    #[Test]
    public function scalar_fields_accepts_all_31_fields(): void
    {
        $discriminator = new Discriminator(propertyName: 'type');
        $xml = new Xml();
        $contentSchema = new Schema(type: 'object');

        $fields = new ScalarFields(
            ref: '#/components/schemas/Foo',
            refSummary: 'Summary',
            refDescription: 'Description',
            format: 'date-time',
            title: 'Title',
            description: 'Desc',
            default: 'def',
            hasDefault: true,
            deprecated: true,
            readOnly: true,
            writeOnly: true,
            type: 'string',
            const: 'constant',
            hasConst: true,
            multipleOf: 2.0,
            maximum: 100.0,
            exclusiveMaximum: 101.0,
            minimum: 0.0,
            exclusiveMinimum: -1.0,
            maxLength: 50,
            minLength: 1,
            pattern: '^[a-z]+$',
            example: 'ex',
            examples: ['ex1'],
            enum: ['a', 'b'],
            contentEncoding: 'base64',
            contentMediaType: 'application/json',
            contentSchema: $contentSchema,
            jsonSchemaDialect: 'https://example.com/dialect',
            discriminator: $discriminator,
            xml: $xml,
        );

        self::assertSame('#/components/schemas/Foo', $fields->ref);
        self::assertSame('Summary', $fields->refSummary);
        self::assertSame('Description', $fields->refDescription);
        self::assertSame('date-time', $fields->format);
        self::assertSame('Title', $fields->title);
        self::assertSame('Desc', $fields->description);
        self::assertSame('def', $fields->default);
        self::assertTrue($fields->hasDefault);
        self::assertTrue($fields->deprecated);
        self::assertTrue($fields->readOnly);
        self::assertTrue($fields->writeOnly);
        self::assertSame('string', $fields->type);
        self::assertSame('constant', $fields->const);
        self::assertTrue($fields->hasConst);
        self::assertSame(2.0, $fields->multipleOf);
        self::assertSame(100.0, $fields->maximum);
        self::assertSame(101.0, $fields->exclusiveMaximum);
        self::assertSame(0.0, $fields->minimum);
        self::assertSame(-1.0, $fields->exclusiveMinimum);
        self::assertSame(50, $fields->maxLength);
        self::assertSame(1, $fields->minLength);
        self::assertSame('^[a-z]+$', $fields->pattern);
        self::assertSame('ex', $fields->example);
        self::assertSame(['ex1'], $fields->examples);
        self::assertSame(['a', 'b'], $fields->enum);
        self::assertSame('base64', $fields->contentEncoding);
        self::assertSame('application/json', $fields->contentMediaType);
        self::assertSame($contentSchema, $fields->contentSchema);
        self::assertSame('https://example.com/dialect', $fields->jsonSchemaDialect);
        self::assertSame($discriminator, $fields->discriminator);
        self::assertSame($xml, $fields->xml);
    }

    #[Test]
    public function scalar_fields_type_accepts_union_of_string_and_list(): void
    {
        $single = new ScalarFields(type: 'object');
        $multiple = new ScalarFields(type: ['object', 'null']);

        self::assertSame('object', $single->type);
        self::assertSame(['object', 'null'], $multiple->type);
    }

    #[Test]
    public function array_fields_defaults_are_null(): void
    {
        $fields = new ArrayFields();

        self::assertNull($fields->items);
        self::assertNull($fields->prefixItems);
        self::assertNull($fields->minItems);
        self::assertNull($fields->maxItems);
        self::assertNull($fields->uniqueItems);
        self::assertNull($fields->contains);
        self::assertNull($fields->minContains);
        self::assertNull($fields->maxContains);
        self::assertNull($fields->unevaluatedItems);
    }

    #[Test]
    public function array_fields_accepts_all_9_fields(): void
    {
        $items = new Schema(type: 'integer');
        $contains = new Schema(type: 'string');
        $unevaluated = new Schema(type: 'object');

        $fields = new ArrayFields(
            items: $items,
            prefixItems: [$items],
            minItems: 1,
            maxItems: 10,
            uniqueItems: true,
            contains: $contains,
            minContains: 1,
            maxContains: 3,
            unevaluatedItems: $unevaluated,
        );

        self::assertSame($items, $fields->items);
        self::assertSame([$items], $fields->prefixItems);
        self::assertSame(1, $fields->minItems);
        self::assertSame(10, $fields->maxItems);
        self::assertTrue($fields->uniqueItems);
        self::assertSame($contains, $fields->contains);
        self::assertSame(1, $fields->minContains);
        self::assertSame(3, $fields->maxContains);
        self::assertSame($unevaluated, $fields->unevaluatedItems);
    }

    #[Test]
    public function array_fields_items_accepts_bool_form(): void
    {
        $fields = new ArrayFields(items: false);

        self::assertFalse($fields->items);
    }

    #[Test]
    public function object_fields_defaults_are_null(): void
    {
        $fields = new ObjectFields();

        self::assertNull($fields->properties);
        self::assertNull($fields->required);
        self::assertNull($fields->minProperties);
        self::assertNull($fields->maxProperties);
        self::assertNull($fields->additionalProperties);
        self::assertNull($fields->unevaluatedProperties);
        self::assertNull($fields->patternProperties);
        self::assertNull($fields->dependentSchemas);
        self::assertNull($fields->propertyNames);
    }

    #[Test]
    public function object_fields_accepts_all_9_fields(): void
    {
        $properties = ['name' => new Schema(type: 'string')];
        $additional = new Schema(type: 'string');
        $unevaluated = new Schema(type: 'object');
        $patternProps = ['^[a-z]+$' => new Schema(type: 'string')];
        $dependent = ['foo' => new Schema(type: 'object')];
        $propertyNames = new Schema(pattern: '^[a-z]+$');

        $fields = new ObjectFields(
            properties: $properties,
            required: ['name'],
            minProperties: 1,
            maxProperties: 5,
            additionalProperties: $additional,
            unevaluatedProperties: $unevaluated,
            patternProperties: $patternProps,
            dependentSchemas: $dependent,
            propertyNames: $propertyNames,
        );

        self::assertSame($properties, $fields->properties);
        self::assertSame(['name'], $fields->required);
        self::assertSame(1, $fields->minProperties);
        self::assertSame(5, $fields->maxProperties);
        self::assertSame($additional, $fields->additionalProperties);
        self::assertSame($unevaluated, $fields->unevaluatedProperties);
        self::assertSame($patternProps, $fields->patternProperties);
        self::assertSame($dependent, $fields->dependentSchemas);
        self::assertSame($propertyNames, $fields->propertyNames);
    }

    #[Test]
    public function object_fields_additional_properties_accepts_bool_form(): void
    {
        $fields = new ObjectFields(additionalProperties: false);

        self::assertFalse($fields->additionalProperties);
    }

    #[Test]
    public function composition_fields_defaults_are_null(): void
    {
        $fields = new CompositionFields();

        self::assertNull($fields->allOf);
        self::assertNull($fields->anyOf);
        self::assertNull($fields->oneOf);
        self::assertNull($fields->not);
        self::assertNull($fields->if);
        self::assertNull($fields->then);
        self::assertNull($fields->else);
    }

    #[Test]
    public function composition_fields_accepts_all_7_fields(): void
    {
        $allOf = [new Schema(type: 'object')];
        $anyOf = [new Schema(type: 'string')];
        $oneOf = [new Schema(type: 'integer')];
        $not = new Schema(type: 'boolean');
        $if = new Schema(type: 'object');
        $then = new Schema(type: 'string');
        $else = new Schema(type: 'null');

        $fields = new CompositionFields(
            allOf: $allOf,
            anyOf: $anyOf,
            oneOf: $oneOf,
            not: $not,
            if: $if,
            then: $then,
            else: $else,
        );

        self::assertSame($allOf, $fields->allOf);
        self::assertSame($anyOf, $fields->anyOf);
        self::assertSame($oneOf, $fields->oneOf);
        self::assertSame($not, $fields->not);
        self::assertSame($if, $fields->if);
        self::assertSame($then, $fields->then);
        self::assertSame($else, $fields->else);
    }

    #[Test]
    public function composition_fields_not_accepts_bool_form(): void
    {
        $fields = new CompositionFields(not: false);

        self::assertFalse($fields->not);
    }
}
