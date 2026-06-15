<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\Numeric\FloatDoubleValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

use function sprintf;

use const INF;
use const NAN;

final class FloatDoubleValidatorTest extends TestCase
{
    private FloatDoubleValidator $floatValidator;
    private FloatDoubleValidator $doubleValidator;

    protected function setUp(): void
    {
        $this->floatValidator = new FloatDoubleValidator('float');
        $this->doubleValidator = new FloatDoubleValidator('double');
    }

    #[Test]
    public function valid_float(): void
    {
        $this->expectNotToPerformAssertions();
        $this->floatValidator->validate(3.14);
        $this->floatValidator->validate(0.0);
        $this->floatValidator->validate(-1.5);
    }

    #[Test]
    public function valid_double(): void
    {
        $this->expectNotToPerformAssertions();
        $this->doubleValidator->validate(3.14);
        $this->doubleValidator->validate(0.0);
        $this->doubleValidator->validate(-1.5);
    }

    #[Test]
    public function valid_scientific_notation(): void
    {
        $this->expectNotToPerformAssertions();
        $this->floatValidator->validate(1.5e10);
        $this->floatValidator->validate(1.5E-10);
        $this->doubleValidator->validate(1.5e10);
        $this->doubleValidator->validate(1.5E-10);
    }

    #[Test]
    public function throw_error_for_integer(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a float');
        $this->floatValidator->validate(42);
    }

    #[Test]
    public function throw_error_for_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a float');
        $this->floatValidator->validate('3.14');
    }

    #[Test]
    public function throw_error_for_string_with_double_validator(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a double');
        $this->doubleValidator->validate('not-a-double');
    }

    #[Test]
    public function throw_exception_for_invalid_format(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Format must be "float" or "double"');
        new FloatDoubleValidator('invalid');
    }

    /**
     * Special IEEE 754 float values: INF, -INF, NAN.
     *
     * The validator uses is_float() to validate. In PHP, INF, -INF, and NAN
     * are all of type float, so they pass validation. This is a
     * characterization of the validator's permissive behavior — it does NOT
     * reject these special values as part of float/double format validation.
     *
     * @return array<string, array{0: float, 1: bool}>
     */
    public static function specialFloatValuesProvider(): array
    {
        return [
            'INF is accepted (is_float(INF) === true)' => [INF, true],
            '-INF is accepted (is_float(-INF) === true)' => [-INF, true],
            'NAN is accepted (is_float(NAN) === true)' => [NAN, true],
            'positive float 3.14 is valid' => [3.14, true],
            'negative float -1.5 is valid' => [-1.5, true],
            'zero float 0.0 is valid' => [0.0, true],
            'scientific notation 1.5e10 is valid' => [1.5e10, true],
        ];
    }

    #[DataProvider('specialFloatValuesProvider')]
    #[Test]
    public function special_float_values_match_expected_result(float $value, bool $expectedValid): void
    {
        $exception = null;

        try {
            $this->floatValidator->validate($value);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertSame(
            $expectedValid,
            null === $exception,
            sprintf(
                'Float value %s was expected to be %s but is %s',
                var_export($value, true),
                $expectedValid ? 'valid' : 'invalid',
                null === $exception ? 'valid' : 'invalid: ' . $exception->getMessage(),
            ),
        );
    }

    #[DataProvider('specialFloatValuesProvider')]
    #[Test]
    public function special_float_values_match_expected_result_for_double(float $value, bool $expectedValid): void
    {
        $exception = null;

        try {
            $this->doubleValidator->validate($value);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertSame(
            $expectedValid,
            null === $exception,
            sprintf(
                'Double value %s was expected to be %s but is %s',
                var_export($value, true),
                $expectedValid ? 'valid' : 'invalid',
                null === $exception ? 'valid' : 'invalid: ' . $exception->getMessage(),
            ),
        );
    }

    #[Test]
    public function inf_acceptance_documents_current_validator_limitation(): void
    {
        $exception = null;

        try {
            $this->floatValidator->validate(INF);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            'INF is accepted by current validator because is_float(INF) === true. '
            . 'If this assertion fails, the validator has been updated to reject special IEEE 754 values.',
        );
    }

    #[Test]
    public function nan_acceptance_documents_current_validator_limitation(): void
    {
        $exception = null;

        try {
            $this->floatValidator->validate(NAN);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            'NAN is accepted by current validator because is_float(NAN) === true. '
            . 'If this assertion fails, the validator has been updated to reject special IEEE 754 values.',
        );
    }

    #[Test]
    public function negative_inf_acceptance_documents_current_validator_limitation(): void
    {
        $exception = null;

        try {
            $this->floatValidator->validate(-INF);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            '-INF is accepted by current validator because is_float(-INF) === true. '
            . 'If this assertion fails, the validator has been updated to reject special IEEE 754 values.',
        );
    }
}
