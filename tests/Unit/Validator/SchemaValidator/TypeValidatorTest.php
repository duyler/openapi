<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

use const INF;
use const NAN;

#[CoversClass(TypeValidator::class)]
class TypeValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private TypeValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new TypeValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function validate_string_type(): void
    {
        $schema = new Schema(type: 'string');

        $succeeded = false;

        try {
            $this->validator->validate('hello', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_number_type_with_float(): void
    {
        $schema = new Schema(type: 'number');

        $succeeded = false;

        try {
            $this->validator->validate(3.14, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_number_type_with_integer(): void
    {
        $schema = new Schema(type: 'number');

        $succeeded = false;

        try {
            $this->validator->validate(42, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_integer_type(): void
    {
        $schema = new Schema(type: 'integer');

        $succeeded = false;

        try {
            $this->validator->validate(42, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_boolean_type(): void
    {
        $schema = new Schema(type: 'boolean');

        $succeeded = false;

        try {
            $this->validator->validate(true, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_null_type(): void
    {
        $schema = new Schema(type: 'null');

        $succeeded = false;

        try {
            $this->validator->validate(null, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_array_type(): void
    {
        $schema = new Schema(type: 'array');

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_object_type(): void
    {
        $schema = new Schema(type: 'object');

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_type_mismatch_error_for_invalid_type(): void
    {
        $schema = new Schema(type: 'string');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(123, $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_when_string_for_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate('123', $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_when_object_for_array(): void
    {
        $schema = new Schema(type: 'array');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(['key' => 'value'], $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_when_array_for_object(): void
    {
        $schema = new Schema(type: 'object');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate([1, 2, 3], $schema);
    }

    #[Test]
    public function skip_validation_when_type_is_null(): void
    {
        $schema = new Schema(type: null);

        $succeeded = false;

        try {
            $this->validator->validate('any value', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_type_mismatch_error_for_null_when_not_null_type(): void
    {
        $schema = new Schema(type: 'string');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(null, $schema);
    }

    #[Test]
    public function validate_type_multiple_types(): void
    {
        $schema = new Schema(type: ['string', 'number']);

        $succeeded = false;

        try {
            $this->validator->validate('hello', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_type_multiple_types_with_number(): void
    {
        $schema = new Schema(type: ['string', 'number']);

        $succeeded = false;

        try {
            $this->validator->validate(42, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_integer_type_with_whole_float(): void
    {
        $schema = new Schema(type: 'integer');

        $succeeded = false;

        try {
            $this->validator->validate(3.0, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_type_mismatch_error_for_non_whole_float_as_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(3.14, $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_for_infinite_as_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(INF, $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_for_nan_as_integer(): void
    {
        $schema = new Schema(type: 'integer');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(NAN, $schema);
    }

    #[Test]
    public function validate_union_integer_number_with_whole_float(): void
    {
        $schema = new Schema(type: ['integer', 'number']);

        $succeeded = false;

        try {
            $this->validator->validate(1.0, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_type_mismatch_for_multiple_types(): void
    {
        $schema = new Schema(type: ['string', 'number']);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(true, $schema);
    }

    #[Test]
    public function throw_type_mismatch_error_for_unknown_type(): void
    {
        $schema = new Schema(type: 'unknown_type');

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate('any value', $schema);
    }

    #[Test]
    public function validate_nullable_string_allows_null(): void
    {
        $schema = new Schema(type: 'string', nullable: true);

        $succeeded = false;

        try {
            $this->validator->validate(null, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_array_type_with_empty_array_and_prefer_array_strategy(): void
    {
        $schema = new Schema(type: 'array');
        $context = ValidationContext::create(
            pool: $this->pool,
            emptyArrayStrategy: EmptyArrayStrategy::PreferArray,
        );

        $succeeded = false;

        try {
            $this->validator->validate([], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_type_mismatch_error_for_empty_array_when_strategy_prefers_object(): void
    {
        $schema = new Schema(type: 'array');
        $context = ValidationContext::create(
            pool: $this->pool,
            emptyArrayStrategy: EmptyArrayStrategy::PreferObject,
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate([], $schema, $context);
    }

    #[Test]
    public function throw_type_mismatch_error_for_empty_array_when_strategy_rejects(): void
    {
        $schema = new Schema(type: 'array');
        $context = ValidationContext::create(
            pool: $this->pool,
            emptyArrayStrategy: EmptyArrayStrategy::Reject,
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate([], $schema, $context);
    }

    #[Test]
    public function validate_object_type_with_empty_array_and_prefer_object_strategy(): void
    {
        $schema = new Schema(type: 'object');
        $context = ValidationContext::create(
            pool: $this->pool,
            emptyArrayStrategy: EmptyArrayStrategy::PreferObject,
        );

        $succeeded = false;

        try {
            $this->validator->validate([], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_type_mismatch_error_for_empty_array_as_object_when_strategy_prefers_array(): void
    {
        $schema = new Schema(type: 'object');
        $context = ValidationContext::create(
            pool: $this->pool,
            emptyArrayStrategy: EmptyArrayStrategy::PreferArray,
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate([], $schema, $context);
    }

    #[Test]
    public function throw_type_mismatch_error_for_empty_array_as_object_when_strategy_rejects(): void
    {
        $schema = new Schema(type: 'object');
        $context = ValidationContext::create(
            pool: $this->pool,
            emptyArrayStrategy: EmptyArrayStrategy::Reject,
        );

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate([], $schema, $context);
    }

    #[Test]
    public function rejects_null_when_type_array_contains_null_and_nullable_as_type_disabled(): void
    {
        $schema = new Schema(type: ['string', 'null']);
        $context = ValidationContext::create(pool: $this->pool, nullableAsType: false);

        $this->expectException(TypeMismatchError::class);

        $this->validator->validate(null, $schema, $context);
    }

    #[Test]
    public function accepts_string_when_type_array_contains_null_and_nullable_as_type_disabled(): void
    {
        $schema = new Schema(type: ['string', 'null']);
        $context = ValidationContext::create(pool: $this->pool, nullableAsType: false);

        $succeeded = false;

        try {
            $this->validator->validate('hello', $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function accepts_null_when_type_array_contains_null_with_default_nullable_as_type(): void
    {
        $schema = new Schema(type: ['string', 'null']);
        $context = ValidationContext::create(pool: $this->pool, nullableAsType: true);

        $succeeded = false;

        try {
            $this->validator->validate(null, $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
