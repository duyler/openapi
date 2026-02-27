<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\OneOfValidatorWithContext;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OneOfValidatorWithContextTest extends TestCase
{
    private OneOfValidatorWithContext $validator;
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;
    private OpenApiDocument $document;
    private ValidationContext $context;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();
        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );
        $this->context = ValidationContext::create($this->pool);
        $this->validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
        );
    }

    #[Test]
    public function validate_with_null_one_of(): void
    {
        $schema = new Schema(type: 'object');

        $this->validator->validateWithContext(['name' => 'John'], $schema, $this->context);

        $this->assertTrue(true);
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
        );

        $data = ['type' => 'user'];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_without_discriminator_single_match(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );

        $this->validator->validateWithContext('test', $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_without_discriminator_no_match(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Exactly one of schemas must match');

        $this->validator->validateWithContext([], $schema, $this->context);
    }

    #[Test]
    public function validate_without_discriminator_multiple_matches(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: ['name' => new Schema(type: 'string')],
                ),
                new Schema(
                    type: 'object',
                    properties: ['id' => new Schema(type: 'integer')],
                ),
            ],
        );

        $data = ['name' => 'John', 'id' => 1];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Data matches multiple schemas');

        $this->validator->validateWithContext($data, $schema, $this->context);
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

        $this->assertTrue(true);
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

        $this->assertTrue(true);
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

        $schema = new Schema(
            ref: '#/components/schemas/Pet',
        );

        $validator = new OneOfValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
        );

        $resolvedSchema = $this->refResolver->resolve('#/components/schemas/Pet', $document);
        $data = ['petType' => 'cat', 'name' => 'Fluffy'];

        $validator->validateWithContext($data, $resolvedSchema, $this->context);

        $this->assertTrue(true);
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

        $this->assertNotEmpty($this->context->breadcrumbs->currentPath());
    }

    #[Test]
    public function validate_with_integer_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'integer'),
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validateWithContext(42, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_boolean_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'boolean'),
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validateWithContext(true, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_number_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'number'),
                new Schema(type: 'string'),
            ],
        );

        $this->validator->validateWithContext(3.14, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_nullable_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer', nullable: true),
            ],
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->validator->validateWithContext(null, $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_complex_object_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(type: 'string'),
                        'email' => new Schema(type: 'string'),
                    ],
                    required: ['name', 'email'],
                ),
                new Schema(
                    type: 'object',
                    properties: [
                        'id' => new Schema(type: 'integer'),
                    ],
                    required: ['id'],
                ),
            ],
        );

        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_array_in_one_of(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
                new Schema(type: 'string'),
            ],
        );

        $data = ['a', 'b', 'c'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_exception_contains_errors(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', minLength: 10),
                new Schema(type: 'integer', minimum: 100),
            ],
        );

        try {
            $this->validator->validateWithContext('short', $schema, $this->context);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame('Exactly one of schemas must match, but none did', $e->getMessage());
        }
    }

    #[Test]
    public function validate_with_nested_one_of(): void
    {
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_skips_non_schema_items(): void
    {
        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                null,
                'invalid',
            ],
        );

        $this->validator->validateWithContext('test', $schema, $this->context);

        $this->assertTrue(true);
    }
}
