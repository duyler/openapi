<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchemaValidatorWithContextResolveRefTest extends TestCase
{
    private RefResolverInterface $refResolver;
    private ValidatorPool $pool;
    private OpenApiDocument $document;

    protected function setUp(): void
    {
        $this->refResolver = new RefResolver();
        $this->pool = new ValidatorPool();

        $itemSchema = new Schema(
            type: 'object',
            required: ['id'],
            properties: [
                'id' => new Schema(type: 'integer', minimum: 1),
                'name' => new Schema(type: 'string'),
            ],
        );

        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Item' => $itemSchema,
                ],
            ),
        );
    }

    #[Test]
    public function validate_resolves_ref_and_validates_required(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Item');
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'id' => 1,
            'name' => 'Test',
        ];

        $validator->validate($data, $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_resolves_ref_and_throws_for_missing_required(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Item');
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'name' => 'Test',
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($data, $schema);
    }

    #[Test]
    public function validate_resolves_ref_and_throws_for_invalid_type(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Item');
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'id' => 'not-an-integer',
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($data, $schema);
    }

    #[Test]
    public function validate_resolves_ref_and_throws_for_minimum_violation(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Item');
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'id' => 0,
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($data, $schema);
    }

    #[Test]
    public function validate_without_ref_passes(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['id'],
            properties: [
                'id' => new Schema(type: 'integer'),
            ],
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = ['id' => 1];

        $validator->validate($data, $schema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_context_resolves_ref_and_validates(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Item');
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);
        $context = ValidationContext::create($this->pool);

        $data = [
            'id' => 1,
        ];

        $validator->validateWithContext($data, $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_context_resolves_ref_and_throws_for_missing_required(): void
    {
        $schema = new Schema(ref: '#/components/schemas/Item');
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);
        $context = ValidationContext::create($this->pool);

        $data = [];

        $this->expectException(ValidationException::class);
        $validator->validateWithContext($data, $schema, $context);
    }

    #[Test]
    public function validate_nested_ref_in_properties(): void
    {
        $containerSchema = new Schema(
            type: 'object',
            properties: [
                'item' => new Schema(ref: '#/components/schemas/Item'),
            ],
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'item' => [
                'id' => 1,
            ],
        ];

        $validator->validate($data, $containerSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_nested_ref_in_properties_throws_for_invalid_data(): void
    {
        $containerSchema = new Schema(
            type: 'object',
            properties: [
                'item' => new Schema(ref: '#/components/schemas/Item'),
            ],
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'item' => [
                'name' => 'Invalid - missing id',
            ],
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($data, $containerSchema);
    }

    #[Test]
    public function validate_ref_in_items(): void
    {
        $arraySchema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Item'),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            ['id' => 1],
            ['id' => 2, 'name' => 'Test'],
        ];

        $validator->validate($data, $arraySchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_ref_in_items_throws_for_invalid_data(): void
    {
        $arraySchema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Item'),
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            ['name' => 'Invalid - missing id'],
        ];

        $this->expectException(ValidationException::class);
        $validator->validate($data, $arraySchema);
    }

    #[Test]
    public function validate_with_nested_ref_in_allof(): void
    {
        $extendedItemSchema = new Schema(
            allOf: [
                new Schema(ref: '#/components/schemas/Item'),
                new Schema(
                    properties: [
                        'extra' => new Schema(type: 'string'),
                    ],
                ),
            ],
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = [
            'id' => 1,
            'extra' => 'value',
        ];

        $validator->validate($data, $extendedItemSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_nested_ref_in_anyof(): void
    {
        $anyOfSchema = new Schema(
            anyOf: [
                new Schema(ref: '#/components/schemas/Item'),
                new Schema(type: 'string'),
            ],
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = ['id' => 1];

        $validator->validate($data, $anyOfSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_nested_ref_in_oneof(): void
    {
        $oneOfSchema = new Schema(
            oneOf: [
                new Schema(ref: '#/components/schemas/Item'),
                new Schema(type: 'string'),
            ],
        );

        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);

        $data = ['id' => 1];

        $validator->validate($data, $oneOfSchema);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_context_without_ref(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['value'],
            properties: [
                'value' => new Schema(type: 'string'),
            ],
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);
        $context = ValidationContext::create($this->pool);

        $data = ['value' => 'test'];

        $validator->validateWithContext($data, $schema, $context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_with_context_without_ref_throws_for_invalid(): void
    {
        $schema = new Schema(
            type: 'object',
            required: ['value'],
            properties: [
                'value' => new Schema(type: 'string'),
            ],
        );
        $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);
        $context = ValidationContext::create($this->pool);

        $data = [];

        $this->expectException(ValidationException::class);
        $validator->validateWithContext($data, $schema, $context);
    }
}
