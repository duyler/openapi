<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\Numeric;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\Numeric\DoubleValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DoubleValidatorTest extends TestCase
{
    private DoubleValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new DoubleValidator();
    }

    #[Test]
    public function valid_double(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(3.14);
        $this->validator->validate(0.0);
        $this->validator->validate(-1.5);
    }

    #[Test]
    public function throw_error_for_invalid_type(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a double (float)');
        $this->validator->validate('not-a-double');
    }
}
