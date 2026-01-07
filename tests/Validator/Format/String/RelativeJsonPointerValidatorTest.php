<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\RelativeJsonPointerValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RelativeJsonPointerValidatorTest extends TestCase
{
    private RelativeJsonPointerValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RelativeJsonPointerValidator();
    }

    #[Test]
    public function valid_relative_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('1');
        $this->validator->validate('2');
        $this->validator->validate('10');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid Relative JSON Pointer format');
        $this->validator->validate('not-a-pointer');
    }
}
