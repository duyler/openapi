<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\InvalidMultipleOfSchemaException;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function extension_loaded;
use function sprintf;

use const INF;
use const PHP_INT_MAX;
use const NAN;

#[CoversClass(NumericRangeValidator::class)]
class NumericRangeValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private NumericRangeValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new NumericRangeValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function validate_minimum(): void
    {
        $schema = new Schema(type: 'number', minimum: 5);

        $succeeded = false;

        try {
            $this->validator->validate(10, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_maximum(): void
    {
        $schema = new Schema(type: 'number', maximum: 10);

        $succeeded = false;

        try {
            $this->validator->validate(5, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_exclusive_minimum(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 5);

        $succeeded = false;

        try {
            $this->validator->validate(6, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_exclusive_maximum(): void
    {
        $schema = new Schema(type: 'number', exclusiveMaximum: 10);

        $succeeded = false;

        try {
            $this->validator->validate(9, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_multiple_of(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);

        $succeeded = false;

        try {
            $this->validator->validate(10, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_minimum_error(): void
    {
        $schema = new Schema(type: 'number', minimum: 5);

        $this->expectException(MinimumError::class);

        $this->validator->validate(3, $schema);
    }

    #[Test]
    public function throw_maximum_error(): void
    {
        $schema = new Schema(type: 'number', maximum: 10);

        $this->expectException(MaximumError::class);

        $this->validator->validate(15, $schema);
    }

    #[Test]
    public function throw_exclusive_minimum_error(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 5);

        $this->expectException(MinimumError::class);

        $this->validator->validate(5, $schema);
    }

    #[Test]
    public function throw_exclusive_maximum_error(): void
    {
        $schema = new Schema(type: 'number', exclusiveMaximum: 10);

        $this->expectException(MaximumError::class);

        $this->validator->validate(10, $schema);
    }

    #[Test]
    public function throw_multiple_of_error(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);

        $this->expectException(MultipleOfKeywordError::class);

        $this->validator->validate(7, $schema);
    }

    #[Test]
    public function skip_validation_for_non_numeric(): void
    {
        $schema = new Schema(type: 'string', minimum: 5);

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
    public function validate_with_integer_type(): void
    {
        $schema = new Schema(type: 'integer', minimum: 1, maximum: 10);

        $succeeded = false;

        try {
            $this->validator->validate(5, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_with_float_type(): void
    {
        $schema = new Schema(type: 'number', minimum: 1.5, maximum: 10.5);

        $succeeded = false;

        try {
            $this->validator->validate(5.5, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_boundary_values(): void
    {
        $schema = new Schema(type: 'number', minimum: 0, maximum: 100);

        $succeeded = false;

        try {
            $this->validator->validate(0, $schema);
            $this->validator->validate(100, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_multiple_of_with_float(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.5);

        $succeeded = false;

        try {
            $this->validator->validate(2.5, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_multiple_of_with_small_float_step(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.1);

        $succeeded = false;

        try {
            $this->validator->validate(0.3, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_multiple_of_error_for_small_float_step_non_multiple(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.1);

        $this->expectException(MultipleOfKeywordError::class);

        $this->validator->validate(0.35, $schema);
    }

    #[Test]
    public function validate_large_float_multiple_of_small_step(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.1);

        $succeeded = false;

        try {
            $this->validator->validate(1e20, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_when_multiple_of_is_zero(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.0);

        $this->expectException(InvalidMultipleOfSchemaException::class);

        $this->validator->validate(7, $schema);
    }

    #[Test]
    public function throw_multiple_of_error_for_integer_non_multiple(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 3);

        $this->expectException(MultipleOfKeywordError::class);

        $this->validator->validate(10, $schema);
    }

    #[Test]
    public function validate_negative_multiple_of(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: -3);

        $succeeded = false;

        try {
            $this->validator->validate(9, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_whole_float_data_with_integer_multiple_of(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 5);

        $succeeded = false;

        try {
            $this->validator->validate(10.0, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_no_constraints(): void
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
    public function php_int_max_as_integer_passes_validation_without_overflow(): void
    {
        $schema = new Schema(type: 'integer', minimum: 0);

        $succeeded = false;

        try {
            $this->validator->validate(PHP_INT_MAX, $schema);
            $succeeded = true;
        } catch (MinimumError|MaximumError|MultipleOfKeywordError $e) {
            self::fail(sprintf('Expected PHP_INT_MAX to pass integer minimum:0, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function exclusive_minimum_rejects_value_equal_to_boundary(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 10);

        $caught = null;

        try {
            $this->validator->validate(10, $schema);
        } catch (MinimumError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('minimum', $caught->keyword());
        self::assertSame('/exclusiveMinimum', $caught->schemaPath());
        self::assertSame(10.0, $caught->params()['minimum']);
        self::assertSame(10.0, $caught->params()['actual']);
    }

    #[Test]
    public function exclusive_minimum_accepts_value_just_above_boundary(): void
    {
        $schema = new Schema(type: 'number', exclusiveMinimum: 10);

        $succeeded = false;

        try {
            $this->validator->validate(10.001, $schema);
            $succeeded = true;
        } catch (MinimumError $e) {
            self::fail(sprintf('Expected 10.001 to pass exclusiveMinimum:10, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function large_multiple_of_accepts_exact_multiple(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 999_999_999);

        $succeeded = false;

        try {
            $this->validator->validate(999_999_999, $schema);
            $succeeded = true;
        } catch (MultipleOfKeywordError $e) {
            self::fail(sprintf('Expected 999999999 to pass multipleOf:999999999, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function large_multiple_of_rejects_non_multiple(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 999_999_999);

        $caught = null;

        try {
            $this->validator->validate(999_999_998, $schema);
        } catch (MultipleOfKeywordError $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('multipleOf', $caught->keyword());
        self::assertSame('/multipleOf', $caught->schemaPath());
        self::assertSame(999_999_999.0, $caught->params()['multipleOf']);
        self::assertSame(999_999_998, $caught->params()['value']);
    }

    /**
     * @return array<string, array{0: int|float, 1: Schema}>
     */
    public static function provideValidNumericEdgeCases(): array
    {
        return [
            'php_int_max' => [PHP_INT_MAX, new Schema(type: 'integer', minimum: 0)],
            'just_above_exclusive_minimum' => [10.001, new Schema(type: 'number', exclusiveMinimum: 10)],
            'large_multiple_of_exact' => [999_999_999, new Schema(type: 'integer', multipleOf: 999_999_999)],
            'negative_within_minimum' => [-5, new Schema(type: 'integer', minimum: -10)],
            'zero_within_minimum' => [0, new Schema(type: 'integer', minimum: 0)],
            'float_just_below_exclusive_maximum' => [9.999, new Schema(type: 'number', exclusiveMaximum: 10)],
        ];
    }

    #[Test]
    #[DataProvider('provideValidNumericEdgeCases')]
    public function valid_numeric_edge_case_passes(int|float $value, Schema $schema): void
    {
        $succeeded = false;

        try {
            $this->validator->validate($value, $schema);
            $succeeded = true;
        } catch (MinimumError|MaximumError|MultipleOfKeywordError $e) {
            self::fail(sprintf('Expected numeric edge case to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * P-009: JSON per RFC 8259 §6 cannot represent NaN. NaN comparisons
     * always return false in PHP, which previously caused the range
     * validator to silently accept any range constraint.
     */
    #[Test]
    public function rejects_nan_for_numeric_range(): void
    {
        $schema = new Schema(type: 'number', minimum: 0);

        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Numeric value must be finite JSON number, NaN given');

        $this->validator->validate(NAN, $schema);
    }

    #[Test]
    public function rejects_infinity_for_numeric_range(): void
    {
        $schema = new Schema(type: 'number', minimum: 0);

        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Numeric value must be finite JSON number, Infinity given');

        $this->validator->validate(INF, $schema);
    }

    #[Test]
    public function rejects_negative_infinity_for_numeric_range(): void
    {
        $schema = new Schema(type: 'number', maximum: 0);

        $this->expectException(InvalidDataTypeException::class);
        $this->expectExceptionMessage('Numeric value must be finite JSON number, Infinity given');

        $this->validator->validate(-INF, $schema);
    }

    #[Test]
    public function validates_normal_int_after_nan_guard_unchanged(): void
    {
        $schema = new Schema(type: 'integer', minimum: 0, maximum: 10);

        $succeeded = false;

        try {
            $this->validator->validate(5, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected normal int to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * P-019: For integer data with non-integer multipleOf the float
     * relative-epsilon check becomes unreliable once the quotient exceeds
     * ~1e15 (e.g. PHP_INT_MAX / 0.0001). When bcmath is available, the
     * exact decimal modulus check is used; otherwise fail-closed with
     * InvalidMultipleOfSchemaException.
     */
    #[Test]
    public function multiple_of_works_for_small_integers_with_float_step(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 0.0001);

        $succeeded = false;

        try {
            $this->validator->validate(10, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected small integer to pass multipleOf:0.0001, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function multiple_of_large_integer_uses_bcmath_or_throws(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 0.0001);

        if (extension_loaded('bcmath')) {
            $succeeded = false;

            try {
                $this->validator->validate(PHP_INT_MAX, $schema);
                $succeeded = true;
            } catch (InvalidMultipleOfSchemaException $e) {
                self::fail(sprintf(
                    'bcmath available — expected PHP_INT_MAX to pass via bcmod, got: %s',
                    $e->getMessage(),
                ));
            }

            self::assertTrue($succeeded);

            return;
        }

        $this->expectException(InvalidMultipleOfSchemaException::class);
        $this->expectExceptionMessage('Cannot reliably check multipleOf');

        $this->validator->validate(PHP_INT_MAX, $schema);
    }

    #[Test]
    public function multiple_of_integer_modulus_path_unchanged(): void
    {
        $schema = new Schema(type: 'integer', multipleOf: 5);

        $succeeded = false;

        try {
            $this->validator->validate(10, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected integer modulus path to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function multiple_of_float_path_unchanged_after_p019(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.1);

        $succeeded = false;

        try {
            $this->validator->validate(0.3, $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected float multipleOf path to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
