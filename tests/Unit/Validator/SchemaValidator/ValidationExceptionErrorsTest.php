<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Components;
use Duyler\OpenApi\Schema\Model\Discriminator;
use Duyler\OpenApi\Schema\Model\InfoObject;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DiscriminatorDataError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\NotValidationError;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\ReadOnlyPropertyError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Exception\WriteOnlyPropertyError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Schema\OneOfValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

use function count;

#[CoversClass(ReadOnlyWriteOnlyValidator::class)]
#[CoversClass(OneOfValidatorWithContext::class)]
#[CoversClass(ItemsValidator::class)]
#[CoversClass(PrefixItemsValidator::class)]
#[CoversClass(DependentSchemasValidator::class)]
final class ValidationExceptionErrorsTest extends TestCase
{
    private ValidatorPool $pool;
    private RefResolverInterface $refResolver;
    private StatelessValidatorRegistry $statelessValidators;
    private OpenApiDocument $document;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->refResolver = new RefResolver();
        $this->document = new OpenApiDocument(
            '3.1.0',
            new InfoObject('Test API', '1.0.0'),
        );
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function read_only_violation_has_errors_array(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'string', readOnly: true),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool, mode: ValidatorMode::Request);
        $caught = null;

        try {
            $validator->validate(['id' => '123'], $schema, $context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(ReadOnlyPropertyError::class, $errors[0]);
        self::assertSame('readOnly', $errors[0]->keyword());
        self::assertSame('id', $errors[0]->params()['propertyName']);
        self::assertSame('/properties/id/readOnly', $errors[0]->schemaPath());
    }

    #[Test]
    public function write_only_violation_has_errors_array(): void
    {
        $validator = new ReadOnlyWriteOnlyValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'object',
            properties: [
                'password' => new Schema(type: 'string', writeOnly: true),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool, mode: ValidatorMode::Response);
        $caught = null;

        try {
            $validator->validate(['password' => 'secret'], $schema, $context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(WriteOnlyPropertyError::class, $errors[0]);
        self::assertSame('writeOnly', $errors[0]->keyword());
        self::assertSame('password', $errors[0]->params()['propertyName']);
        self::assertSame('/properties/password/writeOnly', $errors[0]->schemaPath());
    }

    #[Test]
    public function oneof_discriminator_non_object_has_errors_array(): void
    {
        $validator = new OneOfValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
            oneOf: [
                new Schema(type: 'object'),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);
        $caught = null;

        try {
            $validator->validateWithContext('string-data', $schema, $context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(DiscriminatorDataError::class, $errors[0]);
        self::assertSame('oneOf', $errors[0]->keyword());
        self::assertSame('/oneOf', $errors[0]->schemaPath());
    }

    #[Test]
    public function oneof_discriminator_null_data_has_errors_array(): void
    {
        $validator = new OneOfValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $schema = new Schema(
            type: 'object',
            discriminator: new Discriminator(propertyName: 'type'),
            oneOf: [
                new Schema(type: 'object'),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);
        $caught = null;

        try {
            $validator->validateWithContext(null, $schema, $context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(DiscriminatorDataError::class, $errors[0]);
        self::assertSame('oneOf', $errors[0]->keyword());
    }

    #[Test]
    public function oneof_multiple_match_has_errors_array(): void
    {
        $validator = new OneOfValidatorWithContext(
            document: $this->document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $schema = new Schema(
            oneOf: [
                new Schema(type: 'string', minLength: 3),
                new Schema(type: 'string', maxLength: 10),
            ],
        );

        $context = ValidationContext::create(pool: $this->pool);
        $caught = null;

        try {
            $validator->validateWithContext('hello', $schema, $context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(OneOfError::class, $errors[0]);
        self::assertSame('oneOf', $errors[0]->keyword());
        self::assertSame('/oneOf', $errors[0]->schemaPath());
    }

    /**
     * P-010: stdClass is now normalized to an array by SchemaValueNormalizer,
     * so passing `[new stdClass()]` against `items.type: string` reports a
     * TypeMismatchError with actual='array' instead of the prior
     * InvalidDataTypeException-wrapped 'object'.
     */
    #[Test]
    public function items_validator_invalid_item_has_errors_array(): void
    {
        $validator = new ItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'string'),
        );

        $caught = null;

        try {
            $validator->validate([new stdClass()], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
        self::assertSame('string', $errors[0]->params()['expected']);
        self::assertSame('array', $errors[0]->params()['actual']);
        self::assertSame('/type', $errors[0]->schemaPath());
        self::assertStringContainsString('/0', $errors[0]->dataPath());
    }

    #[Test]
    public function items_validator_validation_failure_propagates_errors(): void
    {
        $validator = new ItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'name' => new Schema(type: 'string'),
                ],
                required: ['name'],
            ),
        );

        $caught = null;

        try {
            $validator->validate([['missing_name' => 'value']], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertGreaterThan(0, count($caught->getErrors()));
    }

    /**
     * P-010: stdClass is now normalized to an array by SchemaValueNormalizer,
     * so `[new stdClass()]` reaches the type validator as `[]` and the
     * schemaPath is the type sub-field of the prefixItems entry rather than
     * the entry itself (as was reported when normalize threw).
     */
    #[Test]
    public function prefix_items_validator_invalid_item_has_errors_array(): void
    {
        $validator = new PrefixItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'string')],
        );

        $caught = null;

        try {
            $validator->validate([new stdClass()], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
        self::assertSame('string', $errors[0]->params()['expected']);
        self::assertSame('/type', $errors[0]->schemaPath());
        self::assertStringContainsString('/0', $errors[0]->dataPath());
    }

    #[Test]
    public function prefix_items_validator_validation_failure_propagates_errors(): void
    {
        $validator = new PrefixItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(
                    not: new Schema(type: 'string'),
                ),
            ],
        );

        $caught = null;

        try {
            $validator->validate(['string_value'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertGreaterThan(0, count($caught->getErrors()));
    }

    #[Test]
    public function dependent_schemas_validator_invalid_has_errors_array(): void
    {
        $validator = new DependentSchemasValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'trigger' => new Schema(
                    type: 'object',
                    properties: ['nested' => new Schema(type: 'string')],
                ),
            ],
        );

        $resource = fopen('php://memory', 'r');
        $caught = null;

        try {
            $validator->validate([
                'trigger' => 'active',
                'nested' => $resource,
            ], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        } finally {
            fclose($resource);
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(InvalidDataTypeException::class, $errors[0]);
        self::assertSame('invalid', $errors[0]->keyword());
        self::assertNotEmpty($errors[0]->message());
    }

    #[Test]
    public function dependent_schemas_validator_validation_failure_propagates_errors(): void
    {
        $validator = new DependentSchemasValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'creditCard' => new Schema(
                    type: 'object',
                    required: ['billingAddress'],
                ),
            ],
        );

        $caught = null;

        try {
            $validator->validate(['creditCard' => '1234567890'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertGreaterThan(0, count($caught->getErrors()));
    }

    #[Test]
    public function dependent_schemas_validator_fallback_when_inner_exception_has_no_errors(): void
    {
        $validator = new DependentSchemasValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'trigger' => new Schema(
                    not: new Schema(type: 'object'),
                ),
            ],
        );

        $caught = null;

        try {
            $validator->validate(['trigger' => 'value'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(NotValidationError::class, $errors[0]);
        self::assertSame('not', $errors[0]->keyword());
        self::assertNotEmpty($errors[0]->message());
    }

    #[Test]
    public function items_validator_fallback_when_inner_exception_has_no_errors(): void
    {
        $validator = new ItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                not: new Schema(type: 'string'),
            ),
        );

        $caught = null;

        try {
            $validator->validate(['string_value'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(NotValidationError::class, $errors[0]);
        self::assertSame('not', $errors[0]->keyword());
        self::assertNotEmpty($errors[0]->message());
    }

    #[Test]
    public function prefix_items_validator_fallback_when_inner_exception_has_no_errors(): void
    {
        $validator = new PrefixItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            prefixItems: [
                new Schema(
                    not: new Schema(type: 'string'),
                ),
            ],
        );

        $caught = null;

        try {
            $validator->validate(['string_value'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(NotValidationError::class, $errors[0]);
        self::assertSame('not', $errors[0]->keyword());
        self::assertNotEmpty($errors[0]->message());
    }

    #[Test]
    public function items_validator_scalar_type_mismatch_wraps_in_validation_exception(): void
    {
        $validator = new ItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
        );

        $caught = null;

        try {
            $validator->validate([1, 'not-int'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
        self::assertSame('integer', $errors[0]->params()['expected']);
        self::assertSame('string', $errors[0]->params()['actual']);
        self::assertStringContainsString('1', $errors[0]->dataPath());
    }

    #[Test]
    public function prefix_items_validator_scalar_type_mismatch_wraps_in_validation_exception(): void
    {
        $validator = new PrefixItemsValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'array',
            prefixItems: [new Schema(type: 'integer')],
        );

        $caught = null;

        try {
            $validator->validate(['not-int'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
        self::assertSame('integer', $errors[0]->params()['expected']);
        self::assertSame('string', $errors[0]->params()['actual']);
    }

    #[Test]
    public function dependent_schemas_validator_type_mismatch_wraps_in_validation_exception(): void
    {
        $validator = new DependentSchemasValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
        $schema = new Schema(
            type: 'object',
            dependentSchemas: [
                'trigger' => new Schema(type: 'integer'),
            ],
        );

        $caught = null;

        try {
            $validator->validate(['trigger' => 'value'], $schema);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(TypeMismatchError::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
        self::assertSame('integer', $errors[0]->params()['expected']);
    }

    #[Test]
    public function oneof_discriminator_in_resolved_schema_has_errors_array(): void
    {
        $catSchema = new Schema(
            type: 'object',
            title: 'Cat',
            properties: [
                'petType' => new Schema(type: 'string'),
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

        $validator = new OneOfValidatorWithContext(
            document: $document,
            dependencies: new SchemaValidatorDependencies(
                pool: $this->pool,
                refResolver: $this->refResolver,
                statelessValidators: $this->statelessValidators,
            ),
        );

        $resolvedSchema = $this->refResolver->resolve('#/components/schemas/Pet', $document);
        $context = ValidationContext::create(pool: $this->pool);
        $caught = null;

        try {
            $validator->validateWithContext('non-object-data', $resolvedSchema, $context);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertGreaterThan(0, count($errors));
        self::assertInstanceOf(DiscriminatorDataError::class, $errors[0]);
        self::assertSame('oneOf', $errors[0]->keyword());
    }
}
