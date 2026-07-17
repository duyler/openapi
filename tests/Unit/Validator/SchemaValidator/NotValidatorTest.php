<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(NotValidator::class)]
class NotValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private NotValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new NotValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function validate_when_schema_invalid(): void
    {
        $notSchema = new Schema(type: 'string', minLength: 10);
        $schema = new Schema(not: $notSchema);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_schema_valid(): void
    {
        $notSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(not: $notSchema);

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function skip_when_not_is_null(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_complex_not_schema(): void
    {
        $notSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(not: $notSchema);

        $this->validator->validate(5, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_matching_not_schema(): void
    {
        $notSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(not: $notSchema);

        $this->expectException(ValidationException::class);

        $this->validator->validate(15, $schema);
    }

    #[Test]
    public function validate_with_type_restriction(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(not: $notSchema);

        $this->validator->validate(42, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_prohibited_type(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(not: $notSchema);

        $this->expectException(ValidationException::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function not_with_nullable_true_on_outer_schema_allows_null(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(nullable: true, not: $notSchema);

        $succeeded = false;

        try {
            $this->validator->validate(null, $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected null to pass with nullable outer schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function not_with_nullable_true_on_outer_schema_rejects_matching_string(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(nullable: true, not: $notSchema);

        $caught = null;

        try {
            $this->validator->validate('hello', $schema);
            self::fail('Expected ValidationException for string matching not-schema');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(ValidationException::class, $caught);
        self::assertSame('Data must NOT match the "not" schema', $caught->getMessage());
    }

    #[Test]
    public function not_without_nullable_allows_null(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(not: $notSchema);

        $succeeded = false;

        try {
            $this->validator->validate(null, $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected null to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function not_with_allof_rejects_data_matching_all_subschemas(): void
    {
        $notSchema = new Schema(
            allOf: [
                new Schema(
                    type: 'object',
                    required: ['a'],
                    properties: ['a' => new Schema(type: 'string')],
                ),
                new Schema(
                    type: 'object',
                    required: ['b'],
                    properties: ['b' => new Schema(type: 'integer')],
                ),
            ],
        );
        $schema = new Schema(not: $notSchema);

        $caught = null;

        try {
            $this->validator->validate(['a' => 'x', 'b' => 1], $schema);
            self::fail('Expected ValidationException for data matching all allOf sub-schemas');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(ValidationException::class, $caught);
        self::assertSame('Data must NOT match the "not" schema', $caught->getMessage());
    }

    #[Test]
    public function not_with_allof_allows_data_not_matching_all_subschemas(): void
    {
        $notSchema = new Schema(
            allOf: [
                new Schema(
                    type: 'object',
                    required: ['a'],
                    properties: ['a' => new Schema(type: 'string')],
                ),
                new Schema(
                    type: 'object',
                    required: ['b'],
                    properties: ['b' => new Schema(type: 'integer')],
                ),
            ],
        );
        $schema = new Schema(not: $notSchema);

        $succeeded = false;

        try {
            $this->validator->validate(['a' => 'x'], $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected data missing required property to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function not_with_oneof_rejects_data_matching_exactly_one_subschema(): void
    {
        $notSchema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $schema = new Schema(not: $notSchema);

        $caught = null;

        try {
            $this->validator->validate('hello', $schema);
            self::fail('Expected ValidationException for data matching exactly one oneOf sub-schema');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertInstanceOf(ValidationException::class, $caught);
        self::assertSame('Data must NOT match the "not" schema', $caught->getMessage());
    }

    #[Test]
    public function not_with_oneof_allows_data_matching_neither_subschema(): void
    {
        $notSchema = new Schema(
            oneOf: [
                new Schema(type: 'string'),
                new Schema(type: 'integer'),
            ],
        );
        $schema = new Schema(not: $notSchema);

        $succeeded = false;

        try {
            $this->validator->validate(true, $schema);
            $succeeded = true;
        } catch (ValidationException $e) {
            self::fail(sprintf('Expected boolean data to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function not_failure_throws_validation_exception_with_expected_message(): void
    {
        $notSchema = new Schema(type: 'string');
        $schema = new Schema(not: $notSchema);

        $caught = null;

        try {
            $this->validator->validate('matches', $schema);
            self::fail('Expected ValidationException when data matches not-schema');
        } catch (ValidationException $e) {
            $caught = $e;
        }

        $errors = $caught->getErrors();

        self::assertInstanceOf(ValidationException::class, $caught);
        self::assertSame('Data must NOT match the "not" schema', $caught->getMessage());
        self::assertCount(1, $errors);
        self::assertSame('not', $errors[0]->keyword());
        self::assertSame('/not', $errors[0]->schemaPath());
    }
}
