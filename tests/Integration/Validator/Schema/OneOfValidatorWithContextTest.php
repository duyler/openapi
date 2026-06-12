<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\OneOfValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class OneOfValidatorWithContextTest extends TestCase
{
    private OneOfValidatorWithContext $validator;
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;
    private OpenApiDocument $document;
    private ValidationContext $context;
    private StatelessValidatorRegistry $statelessValidators;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );
        $this->context = ValidationContext::create(pool: $this->pool);
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
        $this->validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
            $this->statelessValidators,
        );
    }

    #[Test]
    public function validate_with_null_one_of(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validateWithContext(['name' => 'John'], $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_discriminator(): void
    {
        $userSchema = new Schema(
            type: 'object',
            title: 'user',
            properties: [
                'type' => new Schema(type: 'string'),
            ],
        );

        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'type',
                mapping: [
                    'user' => '#/components/schemas/User',
                ],
            ),
            oneOf: [
                new Schema(ref: '#/components/schemas/User'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'User' => $userSchema,
                ],
            ),
        );

        $validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
            $this->statelessValidators,
        );

        $data = ['type' => 'user'];

        $validator->validateWithContext($data, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_discriminator_null_data_non_nullable(): void
    {
        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
            oneOf: [
                new Schema(type: 'object'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('data must be an object');

        $this->validator->validateWithContext(null, $schema, $this->context);
    }

    #[Test]
    public function validate_with_discriminator_null_data_nullable(): void
    {
        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
            oneOf: [
                new Schema(type: 'object', nullable: true),
            ],
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validateWithContext(null, $schema, $nullableContext);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_discriminator_non_array_data(): void
    {
        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
            oneOf: [
                new Schema(type: 'object'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('data must be an object');

        $this->validator->validateWithContext('string', $schema, $this->context);
    }

    #[Test]
    public function validate_with_use_discriminator_false(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validateWithContext('test', $schema, $this->context, useDiscriminator: false);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_discriminator_and_ref(): void
    {
        $catSchema = new Schema(
            type: 'object',
            title: 'Cat',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                ],
            ),
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                ],
            ),
        );

        $resolvedSchema = $this->refResolver->resolve('#/components/schemas/Pet', $document);
        $data = ['petType' => 'cat', 'name' => 'Fluffy'];

        $validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
            $this->statelessValidators,
        );

        $validator->validateWithContext($data, $resolvedSchema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_context_breadcrumb_tracking(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validateWithContext('test', $schema, $this->context);

        self::assertNotEmpty($this->context->breadcrumbs->currentPath());
    }

    #[Test]
    public function validate_skips_non_schema_items(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                null,
                'invalid',
                42,
            ],
        );

        $this->validator->validateWithContext('test', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_exception_contains_errors_when_none_match(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', minLength: 10),
                new Schema(type: 'integer', minimum: 100),
            ],
        );

        try {
            $this->validator->validateWithContext('short', $schema, $this->context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('Exactly one of schemas must match, but none did', $e->getMessage());
        }
    }

    #[Test]
    public function validate_without_discriminator_with_ref(): void
    {
        $catSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
            required: ['name'],
        );

        $dogSchema = new Schema(
            type: 'object',
            properties: [
                'breed' => new Schema(type: 'string'),
            ],
            required: ['breed'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
        );

        $data = ['name' => 'Fluffy'];

        $validator->validateWithContext($data, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_nested_one_of_success(): void
    {
        $innerSchema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );

        $schema = new Schema(
            oneOf: [
                $innerSchema,
                new Schema(type: 'boolean'),
            ],
        );

        $this->validator->validateWithContext('test', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_nested_one_of_all_fail(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'integer'),
                new Schema(type: 'boolean'),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext(['array'], $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_with_format_constraint(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', format: 'email'),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('user@example.com', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_pattern_constraint(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', pattern: '^[a-z]+$'),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('hello', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_enum_constraint(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', enum: ['active', 'inactive']),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('active', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_null_with_nullable_schema(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer', nullable: true),
            ],
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validateWithContext(null, $schema, $nullableContext);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_null_without_nullable_schema(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $this->validator->validateWithContext(null, $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_null_with_nullable_as_type_false(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer', nullable: true),
            ],
        );

        $nonNullableContext = ValidationContext::create($this->pool, nullableAsType: false);

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext(null, $schema, $nonNullableContext);
    }

    #[Test]
    public function validate_without_discriminator_all_of_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    allOf: [
                        new Schema(type: 'object', required: ['name']),
                        new Schema(
                            type: 'object',
                            properties: ['name' => new Schema(type: 'string')],
                        ),
                    ],
                ),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext(['name' => 'John'], $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_any_of_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    anyOf: [
                        new Schema(type: 'string', minLength: 3),
                        new Schema(type: 'string', maxLength: 5),
                    ],
                ),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('hi', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_required_properties(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'email' => new Schema(type: 'string'),
                    ],
                    required: ['email'],
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'phone' => new Schema(type: 'string'),
                    ],
                    required: ['phone'],
                ),
            ],
        );

        $data = ['phone' => '+1234567890'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_both_required_present(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'email' => new Schema(type: 'string'),
                    ],
                    required: ['email'],
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'phone' => new Schema(type: 'string'),
                    ],
                    required: ['phone'],
                ),
            ],
        );

        $data = ['email' => 'test@test.com', 'phone' => '+1234567890'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data matches multiple schemas');

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_neither_required_present(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'email' => new Schema(type: 'string'),
                    ],
                    required: ['email'],
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'phone' => new Schema(type: 'string'),
                    ],
                    required: ['phone'],
                ),
            ],
        );

        $data = ['name' => 'John'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_single_schema_match(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext(42, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_single_schema_failure(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'integer'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $this->validator->validateWithContext('not-integer', $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_three_schemas_match_two(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'number'),
                new Schema(type: 'integer'),
                new Schema(type: 'string'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data matches multiple schemas');

        $this->validator->validateWithContext(42, $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_array_vs_object(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'key' => new Schema(type: 'string'),
                    ],
                ),
            ],
        );

        $this->validator->validateWithContext(['a', 'b'], $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_error_contains_abstract_errors(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', minLength: 100),
                new Schema(type: 'integer', minimum: 1000),
            ],
        );

        try {
            $this->validator->validateWithContext('hi', $schema, $this->context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame('Exactly one of schemas must match, but none did', $e->getMessage());
        }
    }

    #[Test]
    public function validate_without_discriminator_generic_exception_in_subschema(): void
    {
        $resource = fopen('php://memory', 'r');

        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(
                    type: 'object',
                    properties: [
                        'data' => new Schema(type: 'string'),
                    ],
                ),
            ],
        );

        try {
            $this->validator->validateWithContext($resource, $schema, $this->context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString('Exactly one of schemas must match', $e->getMessage());
        } finally {
            fclose($resource);
        }
    }

    #[Test]
    public function validate_without_discriminator_with_const_constraint(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(const: 'exact-value', hasConst: true),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('exact-value', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_const_mismatch(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(const: 'exact-value', hasConst: true),
                new Schema(type: 'integer'),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext('other-value', $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_empty_one_of_array(): void
    {
        $schema = new Schema(oneOf: []);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $this->validator->validateWithContext('test', $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_with_additional_properties(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: 'string'),
                    ],
                    additionalProperties: false,
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                    additionalProperties: false,
                ),
            ],
        );

        $data = ['name' => 'John'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_numeric_range(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'integer', minimum: 0, maximum: 10),
                new Schema(type: 'integer', minimum: 100, maximum: 200),
            ],
        );

        $this->validator->validateWithContext(5, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_numeric_range_no_match(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'integer', minimum: 0, maximum: 10),
                new Schema(type: 'integer', minimum: 100, maximum: 200),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext(50, $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_with_boolean_data(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'boolean'),
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validateWithContext(true, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_null_data_nullable_context(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', nullable: true),
                new Schema(type: 'integer'),
            ],
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validateWithContext(null, $schema, $nullableContext);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_all_nullable_schemas(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', nullable: true),
                new Schema(type: 'integer', nullable: true),
            ],
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data matches multiple schemas');

        $this->validator->validateWithContext(null, $schema, $nullableContext);
    }

    public static function singleTypeMatchProvider(): array
    {
        return [
            'string matches string schema' => [
                'test',
                [new Schema(type: 'string'), new Schema(type: 'integer')],
            ],
            'integer matches integer schema' => [
                42,
                [new Schema(type: 'string'), new Schema(type: 'integer')],
            ],
            'float matches number schema' => [
                3.14,
                [new Schema(type: 'number'), new Schema(type: 'string')],
            ],
            'boolean matches boolean schema' => [
                true,
                [new Schema(type: 'boolean'), new Schema(type: 'string')],
            ],
            'integer matches number schema' => [
                42,
                [new Schema(type: 'number'), new Schema(type: 'string')],
            ],
        ];
    }

    #[DataProvider('singleTypeMatchProvider')]
    #[Test]
    public function validate_without_discriminator_single_type_match(
        mixed $data,
        array $oneOf,
    ): void {
        $schema = new Schema(oneOf: $oneOf);

        $this->validator->validateWithContext($data, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    public static function noMatchProvider(): array
    {
        return [
            'array matches neither string nor integer' => [
                ['key' => 'value'],
                [new Schema(type: 'string'), new Schema(type: 'integer')],
            ],
            'boolean matches neither string nor integer' => [
                true,
                [new Schema(type: 'string'), new Schema(type: 'integer')],
            ],
            'null matches neither string nor integer' => [
                null,
                [new Schema(type: 'string'), new Schema(type: 'integer')],
            ],
            'float does not match integer-only schema' => [
                3.14,
                [new Schema(type: 'integer'), new Schema(type: 'boolean')],
            ],
        ];
    }

    #[DataProvider('noMatchProvider')]
    #[Test]
    public function validate_without_discriminator_no_match(
        mixed $data,
        array $oneOf,
    ): void {
        $schema = new Schema(oneOf: $oneOf);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    public static function multipleMatchProvider(): array
    {
        return [
            'integer matches both number and integer' => [
                42,
                [new Schema(type: 'number'), new Schema(type: 'integer')],
            ],
            'empty object matches two loose object schemas' => [
                ['name' => 'test', 'id' => 1],
                [
                    new Schema(type: 'object', properties: ['name' => new Schema(type: 'string')]),
                    new Schema(type: 'object', properties: ['id' => new Schema(type: 'integer')]),
                ],
            ],
        ];
    }

    #[DataProvider('multipleMatchProvider')]
    #[Test]
    public function validate_without_discriminator_multiple_matches(
        mixed $data,
        array $oneOf,
    ): void {
        $schema = new Schema(oneOf: $oneOf);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data matches multiple schemas');

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    public static function complexObjectMatchProvider(): array
    {
        return [
            'matches first object schema with email' => [
                ['email' => 'test@example.com'],
                [
                    new Schema(
                        type: 'object',
                        properties: ['email' => new Schema(type: 'string')],
                        required: ['email'],
                    ),
                    new Schema(
                        type: 'object',
                        properties: ['phone' => new Schema(type: 'string')],
                        required: ['phone'],
                    ),
                ],
            ],
            'matches second object schema with phone' => [
                ['phone' => '+1234567890'],
                [
                    new Schema(
                        type: 'object',
                        properties: ['email' => new Schema(type: 'string')],
                        required: ['email'],
                    ),
                    new Schema(
                        type: 'object',
                        properties: ['phone' => new Schema(type: 'string')],
                        required: ['phone'],
                    ),
                ],
            ],
        ];
    }

    #[DataProvider('complexObjectMatchProvider')]
    #[Test]
    public function validate_without_discriminator_complex_object_match(
        mixed $data,
        array $oneOf,
    ): void {
        $schema = new Schema(oneOf: $oneOf);

        $this->validator->validateWithContext($data, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_ref_failure(): void
    {
        $catSchema = new Schema(
            type: 'object',
            properties: [
                'meow' => new Schema(type: 'string'),
            ],
            required: ['meow'],
        );

        $dogSchema = new Schema(
            type: 'object',
            properties: [
                'bark' => new Schema(type: 'string'),
            ],
            required: ['bark'],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $validator->validateWithContext(['unknown' => 'field'], $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_with_ref_multiple_match(): void
    {
        $catSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Cat' => $catSchema,
                    'Pet' => $petSchema,
                ],
            ),
        );

        $validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
            $this->statelessValidators,
        );

        $schema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Pet'),
            ],
        );

        $data = ['name' => 'Fluffy'];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data matches multiple schemas');

        $validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_deeply_nested_one_of(): void
    {
        $level3 = new Schema(
            oneOf: [
                new Schema(type: 'string', minLength: 1),
                new Schema(type: 'integer'),
            ],
        );

        $level2 = new Schema(
            oneOf: [
                $level3,
                new Schema(type: 'boolean'),
            ],
        );

        $schema = new Schema(
            oneOf: [
                $level2,
                new Schema(type: 'number'),
            ],
        );

        $this->validator->validateWithContext('hello', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_not_constraint(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'string',
                    not: new Schema(type: 'string', maxLength: 3),
                ),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('hello', $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_if_then_else(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'level' => new Schema(type: 'string'),
                    ],
                    if: new Schema(required: ['level']),
                    then: new Schema(
                        properties: ['level' => new Schema(type: 'string', enum: ['A', 'B'])],
                    ),
                ),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext(['level' => 'A'], $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_if_then_else_failure(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'level' => new Schema(type: 'string'),
                    ],
                    if: new Schema(required: ['level']),
                    then: new Schema(
                        properties: ['level' => new Schema(type: 'string', enum: ['A', 'B'])],
                    ),
                ),
                new Schema(type: 'integer'),
            ],
        );

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext(['level' => 'C'], $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_with_array_items(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'array',
                    items: new Schema(type: 'integer'),
                ),
                new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ],
        );

        $this->validator->validateWithContext([1, 2, 3], $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_with_mixed_types_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
                new Schema(type: 'boolean'),
                new Schema(type: 'number'),
            ],
        );

        $this->validator->validateWithContext(true, $schema, $this->context);

        self::expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_without_discriminator_error_message_on_no_match(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
                new Schema(type: 'boolean'),
            ],
        );

        try {
            $this->validator->validateWithContext(['array'], $schema, $this->context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString('Exactly one of schemas must match', $e->getMessage());
        }
    }

    #[Test]
    public function validate_without_discriminator_includes_all_errors_when_none_match(): void
    {
        $stringSchema = new Schema(type: 'string', minLength: 10);
        $intSchema = new Schema(type: 'integer', minimum: 100);

        $schema = new Schema(
            oneOf: [$stringSchema, $intSchema],
        );

        // 'short' fails string (minLength 10) AND fails integer (wrong type)
        try {
            $this->validator->validateWithContext('short', $schema, $this->context);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            // The exception message should indicate none matched
            $this->assertStringContainsString('none did', $e->getMessage());
            $this->assertNotEmpty($e->getErrors());
        }
    }

    #[Test]
    public function runtime_exception_in_one_of_is_not_swallowed(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/NonExistent'),
            ],
        );

        $this->expectException(RuntimeException::class);

        $this->validator->validateWithContext(['data' => 'test'], $schema, $this->context);
    }
}
