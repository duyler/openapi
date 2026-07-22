<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression coverage for R4-CORRECTNESS-001 / R4-SEC-001 / R4-ARCH-001:
 * `$ref` inside schema-typed keywords must be resolved before recursion.
 *
 * Each test drives a full {@see SchemaValidatorWithContext::validate()} call
 * so the stateless nested-keyword validators go through the same
 * `AbstractSchemaValidator::createSchemaValidator()` funnel that
 * previously returned a legacy recursion engine without document context —
 * leaving stub `{$ref: '#/...'}` subschemas as no-ops.
 */
final class NestedRefResolutionTest extends TestCase
{
    private OpenApiDocument $document;
    private SchemaValidatorWithContext $validator;

    protected function setUp(): void
    {
        $strict = new Schema(
            type: 'object',
            required: ['value'],
            properties: ['value' => new Schema(type: 'integer', minimum: 0)],
            additionalProperties: false,
        );

        $this->document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Nested $ref', '1.0.0'),
            components: new Components(schemas: [
                'Strict' => $strict,
                'NameRule' => new Schema(
                    type: 'string',
                    pattern: '^[a-z]+$',
                    minLength: 1,
                ),
            ]),
        );

        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $statelessValidators = new StatelessValidatorRegistry(
            $pool,
            BuiltinFormats::create(),
            document: $this->document,
            refResolver: $refResolver,
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: $statelessValidators,
        );

        $this->validator = new SchemaValidatorWithContext($this->document, $dependencies);
    }

    #[Test]
    public function additional_properties_ref_rejects_invalid_value(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'item' => new Schema(
                    type: 'object',
                    additionalProperties: new Schema(ref: '#/components/schemas/Strict'),
                ),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['item' => ['bad' => ['value' => -5]]], $schema);
    }

    #[Test]
    public function additional_properties_ref_accepts_valid_value(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'item' => new Schema(
                    type: 'object',
                    additionalProperties: new Schema(ref: '#/components/schemas/Strict'),
                ),
            ],
        );

        $this->validator->validate(['item' => ['ok' => ['value' => 42]]], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function pattern_properties_ref_rejects_invalid_value(): void
    {
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^k_' => new Schema(ref: '#/components/schemas/Strict'),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['k_bad' => ['value' => -1]], $schema);
    }

    #[Test]
    public function pattern_properties_ref_accepts_valid_value(): void
    {
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^k_' => new Schema(ref: '#/components/schemas/Strict'),
            ],
        );

        $this->validator->validate(['k_ok' => ['value' => 7]], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function property_names_ref_rejects_invalid_property_name(): void
    {
        $schema = new Schema(
            type: 'object',
            propertyNames: new Schema(ref: '#/components/schemas/NameRule'),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['UPPER' => 1], $schema);
    }

    #[Test]
    public function property_names_ref_accepts_valid_property_name(): void
    {
        $schema = new Schema(
            type: 'object',
            propertyNames: new Schema(ref: '#/components/schemas/NameRule'),
        );

        $this->validator->validate(['lower' => 1], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function contains_ref_rejects_value_that_fails_contains_schema(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([['value' => -3], ['value' => -4]], $schema);
    }

    #[Test]
    public function contains_ref_accepts_array_with_matching_item(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->validator->validate([['value' => -3], ['value' => 11]], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function not_ref_rejects_data_matching_referenced_schema(): void
    {
        $schema = new Schema(
            not: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['value' => 1], $schema);
    }

    #[Test]
    public function not_ref_accepts_data_that_does_not_match_referenced_schema(): void
    {
        $schema = new Schema(
            not: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->validator->validate(['value' => -1], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function if_then_else_ref_routes_then_branch_via_ref(): void
    {
        $schema = new Schema(
            if: new Schema(type: 'object', required: ['mode'], properties: ['mode' => new Schema(type: 'string', enum: ['strict'])]),
            then: new Schema(
                type: 'object',
                properties: ['payload' => new Schema(ref: '#/components/schemas/Strict')],
            ),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['mode' => 'strict', 'payload' => ['value' => -2]], $schema);
    }

    #[Test]
    public function if_then_else_ref_else_branch_resolves(): void
    {
        $schema = new Schema(
            if: new Schema(type: 'object', required: ['mode'], properties: ['mode' => new Schema(type: 'string', enum: ['strict'])]),
            else: new Schema(
                type: 'object',
                properties: ['payload' => new Schema(ref: '#/components/schemas/Strict')],
            ),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['mode' => 'lax', 'payload' => ['value' => -7]], $schema);
    }

    #[Test]
    public function dependent_schemas_ref_applies_when_property_present(): void
    {
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'lock' => new Schema(ref: '#/components/schemas/Strict'),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['lock' => 1], $schema);
    }

    #[Test]
    public function prefix_items_ref_validates_each_positional_item(): void
    {
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(ref: '#/components/schemas/Strict')],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([['value' => -1]], $schema);
    }

    #[Test]
    public function unevaluated_properties_ref_rejects_invalid_extra_property(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['known' => new Schema(type: 'integer')],
            unevaluatedProperties: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['extra' => ['value' => -1]], $schema);
    }

    #[Test]
    public function unevaluated_items_ref_rejects_invalid_extra_item(): void
    {
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'integer')],
            unevaluatedItems: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([1, ['value' => -2]], $schema);
    }

    #[Test]
    public function circular_self_ref_terminates_without_stack_overflow(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Circular', '1.0.0'),
            components: new Components(schemas: [
                'Node' => new Schema(
                    type: 'object',
                    properties: [
                        'parent' => new Schema(ref: '#/components/schemas/Node'),
                    ],
                ),
            ]),
        );

        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $statelessValidators = new StatelessValidatorRegistry(
            $pool,
            BuiltinFormats::create(),
            document: $document,
            refResolver: $refResolver,
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: $statelessValidators,
        );
        $validator = new SchemaValidatorWithContext($document, $dependencies);

        $validator->validate(
            ['parent' => ['parent' => ['parent' => []]]],
            new Schema(ref: '#/components/schemas/Node'),
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function deeply_circular_ref_bounded_by_max_depth_throws_typed_exception(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Deep circular', '1.0.0'),
            components: new Components(schemas: [
                'Chain' => new Schema(
                    type: 'object',
                    properties: [
                        'next' => new Schema(ref: '#/components/schemas/Chain'),
                    ],
                ),
            ]),
        );

        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $statelessValidators = new StatelessValidatorRegistry(
            $pool,
            BuiltinFormats::create(),
            document: $document,
            refResolver: $refResolver,
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: $statelessValidators,
        );
        $validator = new SchemaValidatorWithContext($document, $dependencies);

        $data = ['next' => null];
        for ($i = 0; $i < 200; ++$i) {
            $data = ['next' => $data];
        }

        $this->expectException(SchemaDepthExceededException::class);

        $validator->validate($data, new Schema(ref: '#/components/schemas/Chain'));
    }

    #[Test]
    public function circular_self_ref_with_nullable_target_accepts_null(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Circular nullable', '1.0.0'),
            components: new Components(schemas: [
                'User' => new Schema(
                    type: 'object',
                    nullable: true,
                    properties: [
                        'parent' => new Schema(
                            nullable: true,
                            ref: '#/components/schemas/User',
                        ),
                    ],
                ),
            ]),
        );

        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $statelessValidators = new StatelessValidatorRegistry(
            $pool,
            BuiltinFormats::create(),
            document: $document,
            refResolver: $refResolver,
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: $statelessValidators,
        );
        $validator = new SchemaValidatorWithContext($document, $dependencies);

        $validator->validate(
            ['parent' => ['parent' => null]],
            new Schema(ref: '#/components/schemas/User'),
        );

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function properties_ref_rejects_invalid_value(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'item' => new Schema(ref: '#/components/schemas/Strict'),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['item' => ['value' => -5]], $schema);
    }

    #[Test]
    public function properties_ref_accepts_valid_value(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'item' => new Schema(ref: '#/components/schemas/Strict'),
            ],
        );

        $this->validator->validate(['item' => ['value' => 42]], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function items_ref_rejects_invalid_value(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([['value' => -1]], $schema);
    }

    #[Test]
    public function items_ref_accepts_valid_value(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Strict'),
        );

        $this->validator->validate([['value' => 7]], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function all_of_with_ref_inside_keyword_combines_constraints(): void
    {
        $schema = new Schema(
            type: 'object',
            allOf: [
                new Schema(ref: '#/components/schemas/Strict'),
                new Schema(required: ['tag']),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['value' => 1], $schema);
    }

    #[Test]
    public function any_of_with_ref_inside_keyword_matches_when_one_branch_passes(): void
    {
        $schema = new Schema(
            anyOf: [
                new Schema(ref: '#/components/schemas/Strict'),
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validate(['value' => 5], $schema);

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function discriminator_with_allof_resolves_via_ref(): void
    {
        $document = new OpenApiDocument(
            '3.2.0',
            new InfoObject('Discriminator+allOf', '1.0.0'),
            components: new Components(schemas: [
                'Cat' => new Schema(
                    type: 'object',
                    title: 'cat',
                    required: ['petType', 'meow'],
                    properties: [
                        'petType' => new Schema(type: 'string'),
                        'meow' => new Schema(type: 'boolean'),
                    ],
                ),
                'Dog' => new Schema(
                    type: 'object',
                    title: 'dog',
                    required: ['petType', 'bark'],
                    properties: [
                        'petType' => new Schema(type: 'string'),
                        'bark' => new Schema(type: 'boolean'),
                    ],
                ),
                'Base' => new Schema(
                    type: 'object',
                    required: ['name'],
                    properties: ['name' => new Schema(type: 'string')],
                ),
                'Polymorph' => new Schema(
                    discriminator: new Discriminator(propertyName: 'petType'),
                    oneOf: [
                        new Schema(ref: '#/components/schemas/Cat'),
                        new Schema(ref: '#/components/schemas/Dog'),
                    ],
                ),
                'Composed' => new Schema(
                    allOf: [
                        new Schema(ref: '#/components/schemas/Base'),
                        new Schema(ref: '#/components/schemas/Polymorph'),
                    ],
                ),
            ]),
        );

        $pool = new ValidatorPool();
        $refResolver = new RefResolver();
        $statelessValidators = new StatelessValidatorRegistry(
            $pool,
            BuiltinFormats::create(),
            document: $document,
            refResolver: $refResolver,
        );
        $dependencies = new SchemaValidatorDependencies(
            pool: $pool,
            refResolver: $refResolver,
            statelessValidators: $statelessValidators,
        );
        $validator = new SchemaValidatorWithContext($document, $dependencies);

        $validator->validate(
            ['petType' => 'cat', 'meow' => true, 'name' => 'Felix'],
            new Schema(ref: '#/components/schemas/Composed'),
        );

        $this->addToAssertionCount(1);
    }
}
