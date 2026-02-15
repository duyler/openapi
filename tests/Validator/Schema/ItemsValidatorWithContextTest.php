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

final class ItemsValidatorWithContextTest extends TestCase
{
    private ItemsValidatorWithContext $validator;
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
        $this->validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $this->document,
        );
    }

    #[Test]
    public function validate_items_with_context(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = ['first', 'second', 'third'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_schema(): void
    {
        $itemSchema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
                'name' => new Schema(type: 'string'),
            ],
        );
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = [
            ['id' => 1, 'name' => 'John'],
            ['id' => 2, 'name' => 'Jane'],
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_validation_context(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = ['value1', 'value2'];

        $customContext = ValidationContext::create($this->pool);
        $this->validator->validateWithContext($data, $schema, $customContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_breadcrumb_tracking(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = ['first', 'second', 'third'];

        $context = ValidationContext::create($this->pool);

        $this->validator->validateWithContext($data, $schema, $context);

        $this->assertNotEmpty($context->breadcrumbs->currentPath());
    }

    #[Test]
    public function validate_items_with_nested_schemas(): void
    {
        $innerItemSchema = new Schema(type: 'string');
        $itemSchema = new Schema(
            type: 'array',
            items: $innerItemSchema,
        );
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = [
            ['a', 'b'],
            ['c', 'd'],
        ];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_empty_array(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = [];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_throws_exception_with_context(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = ['valid', 123, 'also valid'];

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_items_when_schema_has_no_items(): void
    {
        $schema = new Schema(type: 'array');

        $data = ['first', 'second'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_multiple_errors(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = ['valid', 123, 'also valid', 456];

        try {
            $this->validator->validateWithContext($data, $schema, $this->context);
            $this->fail('Expected ValidationException to be thrown');
        } catch (ValidationException $e) {
            $this->assertNotEmpty($e->getErrors());
        }
    }

    #[Test]
    public function validate_items_with_discriminator_schema(): void
    {
        $itemSchema = new Schema(
            ref: '#/components/schemas/Pet',
        );

        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
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

        $validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
        );

        $data = [
            ['petType' => 'cat'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_integer_schema(): void
    {
        $itemSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = [1, 2, 3, 4, 5];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_number_schema(): void
    {
        $itemSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = [1.5, 2.7, 3.14];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_boolean_schema(): void
    {
        $itemSchema = new Schema(type: 'boolean');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = [true, false, true];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_discriminator_in_nested_schema(): void
    {
        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
            ),
        );

        $schema = new Schema(
            type: 'array',
            items: new Schema(
                ref: '#/components/schemas/Pet',
            ),
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

        $validator = new ItemsValidatorWithContext(
            $this->pool,
            $this->refResolver,
            $document,
        );

        $data = [
            ['petType' => 'cat'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }
}
