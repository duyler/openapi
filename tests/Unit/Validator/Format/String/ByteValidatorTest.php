<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\ByteValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ByteValidatorTest extends TestCase
{
    private ByteValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ByteValidator();
    }

    #[Test]
    public function valid_base64_string(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(base64_encode('Hello, World!'));
        $this->validator->validate('SGVsbG8sIFdvcmxkIQ==');
    }

    #[Test]
    public function valid_empty_base64(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('');
    }

    #[Test]
    public function throw_error_for_invalid_characters(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid base64 format');
        $this->validator->validate('!@#$%^&*()');
    }

    #[Test]
    public function validate_unicode_base64(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate(base64_encode('Привет мир'));
    }

    #[Test]
    public function validate_binary_data(): void
    {
        $this->expectNotToPerformAssertions();
        $binaryData = pack('C*', 0, 1, 2, 3, 255);
        $this->validator->validate(base64_encode($binaryData));
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
