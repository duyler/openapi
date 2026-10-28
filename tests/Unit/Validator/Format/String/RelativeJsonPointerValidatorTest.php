<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\RelativeJsonPointerValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(RelativeJsonPointerValidator::class)]
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

    #[Test]
    public function valid_zero_relative_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('0');
    }

    #[Test]
    public function valid_with_hash(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('1#');
    }

    #[Test]
    public function valid_with_json_pointer(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('1');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }

    #[Test]
    public function throw_error_for_leading_zero(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid Relative JSON Pointer format');
        $this->validator->validate('01');
    }

    #[Test]
    public function throw_error_for_invalid_json_pointer(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid Relative JSON Pointer format');
        $this->validator->validate('1~2');
    }

    public static function suiteConformanceProvider(): array
    {
        return [
            'downwards pointer is valid' => ['0/foo/bar', true],
            'up and then down pointer is valid' => ['2/0/baz/1/zip', true],
            'octothorpe variant is valid' => ['0#', true],
            'multi-digit integer prefix is valid' => ['120/foo/bar', true],
            'multi-digit prefix with internal zero is valid' => ['100', true],
            'empty reference tokens are valid' => ['0//', true],
            'json-pointer without integer prefix is invalid' => ['/foo/bar', false],
            'pointer part without leading slash is invalid' => ['1foo', false],
            'negative prefix is invalid' => ['-1/foo/bar', false],
            'explicit positive prefix is invalid' => ['+1/foo/bar', false],
            'non-ascii digit in the prefix is invalid' => ["\u{661}/foo", false],
            'double octothorpe is invalid' => ['0##', false],
            'zero followed by digits and pointer is invalid' => ['01/a', false],
            'zero followed by digits and octothorpe is invalid' => ['01#', false],
            'empty string is invalid' => ['', false],
            'tilde not followed by zero or one is invalid' => ['0/~2', false],
            'unescaped tilde at the end is invalid' => ['0/foo/bar~', false],
            'octothorpe followed by pointer is invalid' => ['1#/foo/bar', false],
            'trailing newline is invalid' => ["1\n", false],
        ];
    }

    #[DataProvider('suiteConformanceProvider')]
    #[Test]
    public function suite_conformance_cases(string $value, bool $expectedValid): void
    {
        if (true === $expectedValid) {
            $this->validator->validate($value);

            $this->expectNotToPerformAssertions();

            return;
        }

        $this->expectException(InvalidFormatException::class);

        $this->validator->validate($value);
    }
}
