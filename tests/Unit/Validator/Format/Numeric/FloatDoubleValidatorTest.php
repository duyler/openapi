<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\Numeric\FloatDoubleValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

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
}
