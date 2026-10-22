<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

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

#[CoversClass(Schema::class)]
final class SchemaConstraintGroupsTest extends TestCase
{
    #[Test]
    public function from_constraint_groups_with_empty_groups_produces_default_schema(): void
    {
        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertNull($schema->type);
        self::assertFalse($schema->nullable);
        self::assertFalse($schema->hasDefault);
        self::assertFalse($schema->deprecated);
        self::assertFalse($schema->readOnly);
        self::assertFalse($schema->writeOnly);
        self::assertFalse($schema->hasConst);
        self::assertNull($schema->format);
        self::assertNull($schema->properties);
        self::assertNull($schema->items);
        self::assertNull($schema->allOf);
    }

    #[Test]
    public function from_constraint_groups_nullable_defaults_to_false_when_null(): void
    {
        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: null,
        );

        self::assertFalse($schema->nullable);
    }

    #[Test]
    public function from_constraint_groups_nullable_can_be_set_to_true(): void
    {
        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: true,
        );

        self::assertTrue($schema->nullable);
    }

    #[Test]
    public function from_constraint_groups_propagates_scalar_fields(): void
    {
        $discriminator = new Discriminator(propertyName: 'type');
        $contentSchema = new Schema(type: 'object');

        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(
                ref: '#/foo',
                format: 'date-time',
                title: 'Title',
                type: 'string',
                minLength: 3,
                maxLength: 50,
                pattern: '^[a-z]+$',
                multipleOf: 2.0,
                minimum: 0.0,
                maximum: 100.0,
                exclusiveMinimum: -1.0,
                exclusiveMaximum: 101.0,
                enum: ['a', 'b'],
                const: 'c',
                hasConst: true,
                default: 'def',
                hasDefault: true,
                deprecated: true,
                readOnly: true,
                writeOnly: true,
                example: 'ex',
                examples: ['ex1'],
                contentEncoding: 'base64',
                contentMediaType: 'application/json',
                contentSchema: $contentSchema,
                jsonSchemaDialect: 'https://example.com',
                discriminator: $discriminator,
            ),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame('#/foo', $schema->ref);
        self::assertSame('date-time', $schema->format);
        self::assertSame('Title', $schema->title);
        self::assertSame('string', $schema->type);
        self::assertSame(3, $schema->minLength);
        self::assertSame(50, $schema->maxLength);
        self::assertSame('^[a-z]+$', $schema->pattern);
        self::assertSame(2.0, $schema->multipleOf);
        self::assertSame(0.0, $schema->minimum);
        self::assertSame(100.0, $schema->maximum);
        self::assertSame(-1.0, $schema->exclusiveMinimum);
        self::assertSame(101.0, $schema->exclusiveMaximum);
        self::assertSame(['a', 'b'], $schema->enum);
        self::assertSame('c', $schema->const);
        self::assertTrue($schema->hasConst);
        self::assertSame('def', $schema->default);
        self::assertTrue($schema->hasDefault);
        self::assertTrue($schema->deprecated);
        self::assertTrue($schema->readOnly);
        self::assertTrue($schema->writeOnly);
        self::assertSame('ex', $schema->example);
        self::assertSame(['ex1'], $schema->examples);
        self::assertSame('base64', $schema->contentEncoding);
        self::assertSame('application/json', $schema->contentMediaType);
        self::assertSame($contentSchema, $schema->contentSchema);
        self::assertSame('https://example.com', $schema->jsonSchemaDialect);
        self::assertSame($discriminator, $schema->discriminator);
    }

    #[Test]
    public function from_constraint_groups_propagates_array_fields(): void
    {
        $items = new Schema(type: 'integer');
        $contains = new Schema(type: 'string');
        $unevaluated = new Schema(type: 'object');

        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(
                items: $items,
                prefixItems: [$items],
                minItems: 1,
                maxItems: 10,
                uniqueItems: true,
                contains: $contains,
                minContains: 1,
                maxContains: 3,
                unevaluatedItems: $unevaluated,
            ),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame($items, $schema->items);
        self::assertSame([$items], $schema->prefixItems);
        self::assertSame(1, $schema->minItems);
        self::assertSame(10, $schema->maxItems);
        self::assertTrue($schema->uniqueItems);
        self::assertSame($contains, $schema->contains);
        self::assertSame(1, $schema->minContains);
        self::assertSame(3, $schema->maxContains);
        self::assertSame($unevaluated, $schema->unevaluatedItems);
    }

    #[Test]
    public function from_constraint_groups_propagates_object_fields(): void
    {
        $properties = ['name' => new Schema(type: 'string')];
        $additional = new Schema(type: 'string');
        $patternProps = ['^[a-z]+$' => new Schema(type: 'string')];
        $dependent = ['foo' => new Schema(type: 'object')];
        $propertyNames = new Schema(pattern: '^[a-z]+$');

        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(
                properties: $properties,
                required: ['name'],
                minProperties: 1,
                maxProperties: 5,
                additionalProperties: $additional,
                unevaluatedProperties: false,
                patternProperties: $patternProps,
                dependentSchemas: $dependent,
                propertyNames: $propertyNames,
            ),
            composition: new CompositionFields(),
        );

        self::assertSame($properties, $schema->properties);
        self::assertSame(['name'], $schema->required);
        self::assertSame(1, $schema->minProperties);
        self::assertSame(5, $schema->maxProperties);
        self::assertSame($additional, $schema->additionalProperties);
        self::assertFalse($schema->unevaluatedProperties);
        self::assertSame($patternProps, $schema->patternProperties);
        self::assertSame($dependent, $schema->dependentSchemas);
        self::assertSame($propertyNames, $schema->propertyNames);
    }

    #[Test]
    public function from_constraint_groups_propagates_composition_fields(): void
    {
        $allOf = [new Schema(type: 'object')];
        $anyOf = [new Schema(type: 'string')];
        $oneOf = [new Schema(type: 'integer')];
        $not = new Schema(type: 'boolean');
        $if = new Schema(type: 'object');
        $then = new Schema(type: 'string');
        $else = new Schema(type: 'null');

        $schema = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(
                allOf: $allOf,
                anyOf: $anyOf,
                oneOf: $oneOf,
                not: $not,
                if: $if,
                then: $then,
                else: $else,
            ),
        );

        self::assertSame($allOf, $schema->allOf);
        self::assertSame($anyOf, $schema->anyOf);
        self::assertSame($oneOf, $schema->oneOf);
        self::assertSame($not, $schema->not);
        self::assertSame($if, $schema->if);
        self::assertSame($then, $schema->then);
        self::assertSame($else, $schema->else);
    }

    #[Test]
    public function from_constraint_groups_equivalent_to_legacy_constructor(): void
    {
        $properties = ['id' => new Schema(type: 'integer')];

        $viaGroups = Schema::fromConstraintGroups(
            scalar: new ScalarFields(type: 'object', title: 'User'),
            array: new ArrayFields(),
            object: new ObjectFields(
                properties: $properties,
                required: ['id'],
                minProperties: 1,
            ),
            composition: new CompositionFields(),
            nullable: false,
        );

        $viaLegacy = new Schema(
            type: 'object',
            title: 'User',
            properties: $properties,
            required: ['id'],
            minProperties: 1,
            nullable: false,
        );

        self::assertNotSame($viaLegacy, $viaGroups);
        self::assertSame($viaLegacy->type, $viaGroups->type);
        self::assertSame($viaLegacy->title, $viaGroups->title);
        self::assertSame($viaLegacy->properties, $viaGroups->properties);
        self::assertSame($viaLegacy->required, $viaGroups->required);
        self::assertSame($viaLegacy->minProperties, $viaGroups->minProperties);
        self::assertSame($viaLegacy->nullable, $viaGroups->nullable);
    }

    #[Test]
    public function with_override_groups_no_overrides_preserves_all_fields(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(type: 'object', title: 'User'),
            array: new ArrayFields(minItems: 5),
            object: new ObjectFields(required: ['id']),
            composition: new CompositionFields(),
            nullable: true,
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertNotSame($original, $result);
        self::assertSame('object', $result->type);
        self::assertSame('User', $result->title);
        self::assertSame(5, $result->minItems);
        self::assertSame(['id'], $result->required);
        self::assertTrue($result->nullable);
    }

    #[Test]
    public function with_override_groups_overrides_scalar_fields(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(type: 'string', title: 'Old', minLength: 1),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(type: 'integer', title: 'New', minLength: 5),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame('integer', $result->type);
        self::assertSame('New', $result->title);
        self::assertSame(5, $result->minLength);
    }

    #[Test]
    public function with_override_groups_overrides_nullable(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: false,
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: true,
        );

        self::assertTrue($result->nullable);
    }

    #[Test]
    public function with_override_groups_preserves_nullable_when_null(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: true,
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: null,
        );

        self::assertTrue($result->nullable);
    }

    #[Test]
    public function with_override_groups_overrides_array_fields(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(minItems: 1, maxItems: 10),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(minItems: 5),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame(5, $result->minItems);
        self::assertSame(10, $result->maxItems);
    }

    #[Test]
    public function with_override_groups_overrides_object_fields(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(required: ['a'], minProperties: 1),
            composition: new CompositionFields(),
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(required: ['b']),
            composition: new CompositionFields(),
        );

        self::assertSame(['b'], $result->required);
        self::assertSame(1, $result->minProperties);
    }

    #[Test]
    public function with_override_groups_overrides_composition_fields(): void
    {
        $original = Schema::fromConstraintGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(anyOf: [new Schema(type: 'string')]),
        );

        $newOneOf = [new Schema(type: 'integer')];

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(oneOf: $newOneOf),
        );

        self::assertSame($newOneOf, $result->oneOf);
        self::assertNotNull($result->anyOf);
    }

    #[Test]
    public function with_override_groups_flips_boolean_back_to_false(): void
    {
        $original = new Schema(
            deprecated: true,
            readOnly: true,
            writeOnly: true,
            hasDefault: true,
            hasConst: true,
            nullable: true,
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(
                deprecated: false,
                readOnly: false,
                writeOnly: false,
                hasDefault: false,
                hasConst: false,
            ),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
            nullable: false,
        );

        self::assertFalse($result->deprecated);
        self::assertFalse($result->readOnly);
        self::assertFalse($result->writeOnly);
        self::assertFalse($result->hasDefault);
        self::assertFalse($result->hasConst);
        self::assertFalse($result->nullable);
    }

    #[Test]
    public function with_override_groups_ref_family_replaces_ref(): void
    {
        $original = new Schema(ref: '#/components/schemas/Old');

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(
                ref: '#/components/schemas/New',
                refSummary: 'New summary',
                refDescription: 'New description',
            ),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame('#/components/schemas/New', $result->ref);
        self::assertSame('New summary', $result->refSummary);
        self::assertSame('New description', $result->refDescription);
    }

    #[Test]
    public function with_override_groups_replaces_schema_or_bool_union_fields(): void
    {
        $original = new Schema(
            type: 'object',
            additionalProperties: true,
            unevaluatedProperties: new Schema(type: 'string'),
            contentSchema: false,
        );

        $newAdditional = new Schema(type: 'integer');
        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(contentSchema: true),
            array: new ArrayFields(),
            object: new ObjectFields(
                additionalProperties: $newAdditional,
                unevaluatedProperties: false,
            ),
            composition: new CompositionFields(),
        );

        self::assertSame($newAdditional, $result->additionalProperties);
        self::assertFalse($result->unevaluatedProperties);
        self::assertTrue($result->contentSchema);
    }

    #[Test]
    public function with_override_groups_replaces_discriminator_and_xml(): void
    {
        $original = new Schema(
            xml: new Xml(name: 'old'),
            discriminator: new Discriminator(propertyName: 'type', mapping: ['a' => '#/A']),
        );

        $newXml = new Xml(name: 'new', wrapped: true);
        $newDiscriminator = new Discriminator(propertyName: 'kind');

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(xml: $newXml, discriminator: $newDiscriminator),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame($newXml, $result->xml);
        self::assertSame($newDiscriminator, $result->discriminator);
    }

    #[Test]
    public function with_override_groups_replaces_default_with_explicit_value(): void
    {
        $original = new Schema(
            type: 'string',
            default: 'old',
            hasDefault: true,
        );

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(default: '', hasDefault: false),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame('', $result->default);
        self::assertFalse($result->hasDefault);
    }

    #[Test]
    public function with_override_groups_is_immutable_original_unchanged(): void
    {
        $original = new Schema(type: 'string', title: 'Original');

        $result = $original->withOverrideGroups(
            scalar: new ScalarFields(type: 'integer'),
            array: new ArrayFields(),
            object: new ObjectFields(),
            composition: new CompositionFields(),
        );

        self::assertSame('string', $original->type);
        self::assertSame('Original', $original->title);
        self::assertSame('integer', $result->type);
        self::assertSame('Original', $result->title);
    }

    #[Test]
    public function with_override_groups_chains(): void
    {
        $schema = new Schema(type: 'object');
        $array = new ArrayFields();
        $object = new ObjectFields();
        $composition = new CompositionFields();

        $chained = $schema
            ->withOverrideGroups(scalar: new ScalarFields(title: 'First'), array: $array, object: $object, composition: $composition)
            ->withOverrideGroups(scalar: new ScalarFields(description: 'Second'), array: $array, object: $object, composition: $composition)
            ->withOverrideGroups(scalar: new ScalarFields(deprecated: true), array: $array, object: $object, composition: $composition);

        self::assertSame('object', $chained->type);
        self::assertSame('First', $chained->title);
        self::assertSame('Second', $chained->description);
        self::assertTrue($chained->deprecated);
    }
}
