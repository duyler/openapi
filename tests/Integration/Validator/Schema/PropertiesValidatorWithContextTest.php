<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Integration\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\PropertiesValidatorWithContext;
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

#[CoversClass(PropertiesValidatorWithContext::class)]
final class PropertiesValidatorWithContextTest extends TestCase
{
    private PropertiesValidatorWithContext $validator;
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
        $this->validator = new PropertiesValidatorWithContext(document: $this->document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));
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

        $customContext = ValidationContext::create(pool: $this->pool);
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

        $context = ValidationContext::create(pool: $this->pool);

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

        $validator = new PropertiesValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

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

        $validator = new PropertiesValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            'pet' => ['petType' => 'Cat'],
        ];

        $validator->validateWithContext($data, $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_discriminator_unknown_mapping_value(): void
    {
        $catSchema = new Schema(
            type: 'object',
            title: 'Cat',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
            ],
        );

        $dogSchema = new Schema(
            type: 'object',
            title: 'Dog',
            properties: [
                'petType' => new Schema(type: 'string'),
                'name' => new Schema(type: 'string'),
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
                    'Cat' => $catSchema,
                    'Dog' => $dogSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            'pet' => ['petType' => 'bird', 'name' => 'Tweety'],
        ];

        $this->expectException(UnknownDiscriminatorValueException::class);

        $validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_properties_with_discriminator_missing_property(): void
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
                    'Cat' => $catSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(document: $document, dependencies: new SchemaValidatorDependencies(pool: $this->pool, refResolver: $this->refResolver, statelessValidators: $this->statelessValidators));

        $data = [
            'pet' => ['name' => 'Fluffy'],
        ];

        $this->expectException(MissingDiscriminatorPropertyException::class);

        $validator->validateWithContext($data, $schema, $this->context);
    }

    #[Test]
    public function validate_properties_with_nullable_property(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', nullable: true),
            ],
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $data = [
            'name' => null,
        ];

        $this->validator->validateWithContext($data, $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_custom_logger_and_event_dispatcher(): void
    {
        $logger = $this->createStub(LoggerInterface::class);
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);

        $validator = new PropertiesValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
                logger: $logger,
                eventDispatcher: $eventDispatcher,
            ),
        );

        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string'),
            ],
        );

        $validator->validateWithContext(['name' => 'John'], $schema, $this->context);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_ref_to_nullable_type_accepts_null(): void
    {
        // Regression for B1: $ref stub to a target with type: ['string', 'null']
        // must accept null. Pre-normalize step sees the unresolved stub; the
        // pre-fix $allowNull calculation rejected null because the stub has no
        // `nullable` and no `type`. Post-fix the ref check lets null through
        // so the inner rootSchemaValidator can resolve $ref and accept null
        // via the resolved target's type.
        $nullableTargetSchema = new Schema(type: ['string', 'null']);

        $schema = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(ref: '#/components/schemas/NullableType'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Nullable Ref API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NullableType' => $nullableTargetSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $validator->validateWithContext(['value' => null], $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_ref_to_non_nullable_type_rejects_null(): void
    {
        // Counter-regression: $ref stub to a target with type: 'string'
        // (non-nullable) must still reject null. The fix lets null past the
        // pre-normalize step, but the inner rootSchemaValidator must resolve
        // $ref and let TypeValidator reject null on the resolved target.
        $nonNullableTargetSchema = new Schema(type: 'string');

        $schema = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(ref: '#/components/schemas/NonNullable'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Non-Nullable Ref API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NonNullable' => $nonNullableTargetSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $this->expectException(ValidationException::class);

        $validator->validateWithContext(['value' => null], $schema, $nullableContext);
    }

    #[Test]
    public function validate_properties_with_ref_and_sibling_nullable_accepts_null(): void
    {
        // README workaround still works: declare `nullable: true` as a sibling
        // of $ref. Resolved target must also allow null per SchemaSiblingMerger
        // AND semantics.
        $nullableTargetSchema = new Schema(type: ['string', 'null']);

        $schema = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(ref: '#/components/schemas/NullableType', nullable: true),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Sibling Nullable Ref API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NullableType' => $nullableTargetSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $validator->validateWithContext(['value' => null], $schema, $nullableContext);

        $this->assertTrue(true);
    }

    #[Test]
    public function validate_properties_with_ref_continues_to_accept_non_null_values(): void
    {
        // Non-regression: non-null values for $ref properties must continue
        // to validate as before. The fix only affects the null-handling path.
        $nullableTargetSchema = new Schema(type: ['string', 'null']);

        $schema = new Schema(
            type: 'object',
            properties: [
                'value' => new Schema(ref: '#/components/schemas/NullableType'),
            ],
        );

        $document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Non-Null Ref API', '1.0.0'),
            components: new Components(
                schemas: [
                    'NullableType' => $nullableTargetSchema,
                ],
            ),
        );

        $validator = new PropertiesValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $nullableContext = ValidationContext::create($this->pool, nullableAsType: true);

        $validator->validateWithContext(['value' => 'hello'], $schema, $nullableContext);

        $this->assertTrue(true);
    }
}
