<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\JsonPointerValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonPointerValidator::class)]
final class JsonPointerValidatorTest extends TestCase
{
    private JsonPointerValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JsonPointerValidator();
    }

    #[Test]
    public function valid_simple_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('/path');
        $this->validator->validate('/property');
    }

    #[Test]
    public function valid_nested_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('/path/to/property');
        $this->validator->validate('/a/b/c');
    }

    #[Test]
    public function valid_with_escaped_chars(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('/path~0with~1tilde');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid JSON Pointer format');
        $this->validator->validate('path');
    }

    #[Test]
    public function valid_root_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('/');
    }

    #[Test]
    public function valid_empty_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('');
    }

    #[Test]
    public function valid_with_numbers(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('/0');
        $this->validator->validate('/1/2/3');
    }

    #[Test]
    public function throw_error_for_invalid_escape(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid JSON Pointer format');
        $this->validator->validate('/path~2');
    }

    #[Test]
    public function throw_error_for_invalid_escape_two_inside_segment(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid JSON Pointer format');

        $this->validator->validate('/foo~2bar');
    }

    #[Test]
    public function throw_error_for_invalid_escape_letter(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid JSON Pointer format');

        $this->validator->validate('/foo~a');
    }

    #[Test]
    public function throw_error_for_trailing_tilde(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid JSON Pointer format');

        $this->validator->validate('/foo~');
    }

    #[Test]
    public function valid_tilde_zero_escape_in_middle(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('/foo~0bar');
    }

    #[Test]
    public function valid_tilde_one_escape_in_middle(): void
    {
        $this->expectNotToPerformAssertions();

        $this->validator->validate('/foo~1bar');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
