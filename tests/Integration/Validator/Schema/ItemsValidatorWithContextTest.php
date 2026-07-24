<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\ItemsValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

#[CoversClass(ItemsValidatorWithContext::class)]
final class ItemsValidatorWithContextTest extends TestCase
{
    private ItemsValidatorWithContext $validator;
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
        $this->validator = new ItemsValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));
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

        $customContext = ValidationContext::create(pool: $this->pool);
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

        $context = ValidationContext::create(pool: $this->pool);

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
                mapping: [
                    'cat' => '#/components/schemas/Pet',
                ],
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

        $validator = new ItemsValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

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
                mapping: [
                    'cat' => '#/components/schemas/Pet',
                ],
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

        $validator = new ItemsValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            ['petType' => 'cat'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_throws_missing_discriminator_property(): void
    {
        $this->expectException(MissingDiscriminatorPropertyException::class);

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Pet',
                ],
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

        $validator = new ItemsValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            ['name' => 'Fluffy'],
        ];
        $validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_items_throws_unknown_discriminator_value(): void
    {
        $this->expectException(UnknownDiscriminatorValueException::class);

        $catSchema = new Schema(
            type: 'object',
            title: 'Cat',
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            title: 'Dog',
            properties: [
                'petType' => new Schema(type: 'string'),
            ],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
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
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $validator = new ItemsValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            ['petType' => 'bird'],
        ];
        $validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_items_with_nullable_item_schema(): void
    {
        $itemSchema = new Schema(type: 'string', nullable: true);
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $data = ['valid', null, 'also valid'];

        $this->validator->validateWithContext($data, $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_mixed_valid_and_invalid(): void
    {
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $data = ['valid', 123, null, 'also valid'];

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_items_with_prefix_items_overlap_skips_prefixed_indices(): void
    {
        $prefixSchema = new Schema(type: 'integer');
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
            items: $itemSchema,
        );

        $data = [42, 'second', 'third'];

        $this->validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_prefix_items_overlap_throws_for_items_after_prefix(): void
    {
        $prefixSchema = new Schema(type: 'integer');
        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            prefixItems: [$prefixSchema],
            items: $itemSchema,
        );

        $data = [42, 123, 'valid'];

        $this->expectException(ValidationException::class);

        $this->validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_items_with_custom_logger(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $validator = new ItemsValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
                logger: $logger,
                eventDispatcher: $eventDispatcher,
            ),
        );

        $itemSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            items: $itemSchema,
        );

        $validator->validateWithContext(['valid'], $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_discriminator_routes_each_element_by_type(): void
    {
        $catSchema = new Schema(
            type: 'object',
            title: 'Cat',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'indoor' => new Schema(type: 'boolean'),
            ],
            required: ['petType', 'name'],
        );

        $dogSchema = new Schema(
            type: 'object',
            title: 'Dog',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
                'breed' => new Schema(type: 'string'),
            ],
            required: ['petType', 'name', 'breed'],
        );

        $petSchema = new Schema(
            type: 'object',
            discriminator: new Discriminator(
                propertyName: 'petType',
                mapping: [
                    'cat' => '#/components/schemas/Cat',
                    'dog' => '#/components/schemas/Dog',
                ],
            ),
            oneOf: [
                new Schema(ref: '#/components/schemas/Cat'),
                new Schema(ref: '#/components/schemas/Dog'),
            ],
        );

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/Pet'),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Pet API', '1.0.0'),
            components: new Components(
                schemas: [
                    'Pet' => $petSchema,
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $validator = new ItemsValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            ['petType' => 'cat', 'name' => 'Fluffy', 'indoor' => true],
            ['petType' => 'dog', 'name' => 'Rex', 'breed' => 'German Shepherd'],
            ['petType' => 'cat', 'name' => 'Whiskers'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_ref_to_nullable_type_accepts_null(): void
    {
        // Regression for B1 (items path): $ref stub to a target with
        // type: ['string', 'null'] must accept null items. The pre-fix
        // $allowNull calculation rejected null because the stub has no
        // `nullable` and no `type`. Post-fix the ref check lets null
        // through so the inner rootSchemaValidator can resolve $ref and
        // accept null via the resolved target's type.
        $nullableTargetSchema = new Schema(type: ['string', 'null']);

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/NullableType'),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Nullable Ref Items API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NullableType' => $nullableTargetSchema,
                ],
            ),
        );

        $validator = new ItemsValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $validator->validateWithContext([null], $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_ref_to_non_nullable_type_rejects_null(): void
    {
        // Counter-regression (items path): $ref stub to a target with
        // type: 'string' (non-nullable) must still reject null. The fix
        // lets null past the pre-normalize step, but the inner
        // rootSchemaValidator must resolve $ref and let TypeValidator
        // reject null on the resolved target.
        $nonNullableTargetSchema = new Schema(type: 'string');

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/NonNullable'),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Non-Nullable Ref Items API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NonNullable' => $nonNullableTargetSchema,
                ],
            ),
        );

        $validator = new ItemsValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->expectException(ValidationException::class);

        $validator->validateWithContext([null], $schema, $nullableContext);
    }

    #[Test]
    public function validate_items_with_ref_and_sibling_nullable_accepts_null(): void
    {
        // README workaround still works (items path): declare `nullable: true`
        // as a sibling of $ref. Resolved target must also allow null per
        // SchemaSiblingMerger AND semantics.
        $nullableTargetSchema = new Schema(type: ['string', 'null']);

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/NullableType', nullable: true),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Sibling Nullable Ref Items API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NullableType' => $nullableTargetSchema,
                ],
            ),
        );

        $validator = new ItemsValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $validator->validateWithContext([null], $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_items_with_ref_continues_to_accept_non_null_values(): void
    {
        // Non-regression (items path): non-null values for $ref items must
        // continue to validate as before. The fix only affects the null-
        // handling path.
        $nullableTargetSchema = new Schema(type: ['string', 'null']);

        $schema = new Schema(
            type: 'array',
            items: new Schema(ref: '#/components/schemas/NullableType'),
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Non-Null Ref Items API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NullableType' => $nullableTargetSchema,
                ],
            ),
        );

        $validator = new ItemsValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $validator->validateWithContext(['hello', 'world'], $schema, $nullableContext);

        $this->assertTrue(true);
    }
}
