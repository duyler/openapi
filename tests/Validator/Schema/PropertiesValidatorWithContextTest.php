<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

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

final class PropertiesValidatorWithContextTest extends TestCase
{
    private PropertiesValidatorWithContext $validator;
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
        $this->validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
        );
    }

    #[Test]
    public function validate_properties_with_context(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
        );

        $data = [
            'name' => 'John Doe',
            'age' => 30,
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_required_fields(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'email' => new Schema(type: 'string'),
            ],
            required: ['name', 'email'],
        );

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_validation_context(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
            ],
        );

        $data = [
            'id' => 123,
        ];

        $customContext = ValidationContext::create($this->pool);
        $this->validator->validateWithContext($data, $schema, $customContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_breadcrumb_tracking(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $data = [
            'name' => 'Test',
        ];

        $context = ValidationContext::create($this->pool);

        $this->validator->validateWithContext($data, $schema, $context);

        $this->assertNotEmpty($context->breadcrumbs->currentPath());
    }

    #[Test]
    public function validate_properties_with_nested_schemas(): void
    {
        $addressSchema = new Schema(
            type: 'object',
            properties: [
                'street' => new Schema(type: 'string'),
                'city' => new Schema(type: 'string'),
            ],
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'address' => $addressSchema,
            ],
        );

        $data = [
            'name' => 'John Doe',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
            ],
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_empty_object(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [],
        );

        $data = [];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_throws_exception_with_context(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
            ],
        );

        $data = [
            'name' => 'John Doe',
            'age' => 'invalid',
        ];

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_properties_with_additional_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $data = [
            'name' => 'John Doe',
            'extraField' => 'this is allowed',
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_when_schema_has_no_properties(): void
    {
        $schema = new Schema(type: 'object');

        $data = [
            'name' => 'John Doe',
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_skips_missing_properties(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
                'email' => new Schema(type: 'string'),
            ],
        );

        $data = [
            'name' => 'John Doe',
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_multiple_errors(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'age' => new Schema(type: 'integer'),
                'email' => new Schema(type: 'string'),
            ],
        );

        $data = [
            'name' => 123,
            'age' => 'invalid',
            'email' => 456,
        ];

        try {
            $this->validator->validateWithContext($data, $schema, $this->context);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->getErrors());
        }
    }

    #[Test]
    public function validate_properties_with_various_types(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'stringProp' => new Schema(type: 'string'),
                'intProp' => new Schema(type: 'integer'),
                'numberProp' => new Schema(type: 'number'),
                'boolProp' => new Schema(type: 'boolean'),
            ],
        );

        $data = [
            'stringProp' => 'test',
            'intProp' => 42,
            'numberProp' => 3.14,
            'boolProp' => true,
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_array_property(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                ),
            ],
        );

        $data = [
            'tags' => ['tag1', 'tag2', 'tag3'],
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_discriminator_schema(): void
    {
        $petSchema = new Schema(
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'pet' => new Schema(ref: '#/components/schemas/Pet'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
        );

        $data = [
            'pet' => ['name' => 'Fluffy'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_nested_object(): void
    {
        $addressSchema = new Schema(
            type: 'object',
            properties: [
                'street' => new Schema(type: 'string'),
                'city' => new Schema(type: 'string'),
                'zipCode' => new Schema(type: 'string'),
            ],
        );

        $userSchema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
                'address' => $addressSchema,
            ],
        );

        $data = [
            'name' => 'John Doe',
            'address' => [
                'street' => '123 Main St',
                'city' => 'New York',
                'zipCode' => '10001',
            ],
        ];

        $this->validator->validateWithContext($data, $userSchema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_nested_discriminator_schema(): void
    {
        $catSchema = new Schema(
            title: 'cat',
            type: 'object',
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
            ],
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'pet' => new Schema(
                    ref: '#/components/schemas/Pet',
                ),
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

        $validator = new PropertiesValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
        );

        $data = [
            'pet' => ['petType' => 'cat'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }
}
