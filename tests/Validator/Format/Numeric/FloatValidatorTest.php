<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\Numeric\FloatValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FloatValidatorTest extends TestCase
{
    private FloatValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FloatValidator();
    }

    #[Test]
    public function valid_float(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(3.14);
        $this->validator->validate(0.0);
        $this->validator->validate(-1.5);
    }

    #[Test]
    public function valid_scientific_notation(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(1.5e10);
        $this->validator->validate(1.5E-10);
    }

    #[Test]
    public function throw_error_for_integer(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a float');
        $this->validator->validate(42);
    }

    #[Test]
    public function throw_error_for_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a float');
        $this->validator->validate('3.14');
    }
}
