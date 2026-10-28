<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UriTemplateValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriTemplateValidator::class)]
final class UriTemplateValidatorTest extends TestCase
{
    private UriTemplateValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UriTemplateValidator();
    }

    #[Test]
    public function uri_template_validator_accepts_simple_template(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://api.example.com/users/{userId}');
        $this->validator->validate('/search?q={searchTerms}');
        $this->validator->validate('http://{username}.example.com/');
    }

    #[Test]
    public function uri_template_validator_accepts_operators(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('{/id*}');
        $this->validator->validate('{?list*}');
        $this->validator->validate('{#fragment}');
        $this->validator->validate('{;param}');
        $this->validator->validate('{&query}');
    }

    #[Test]
    public function uri_template_validator_accepts_plain_uri(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://example.com/static/path');
        $this->validator->validate('');
    }

    #[Test]
    public function uri_template_validator_rejects_unbalanced_braces(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Unbalanced template expressions');
        $this->validator->validate('https://api.example.com/{userId');
    }

    #[Test]
    public function uri_template_validator_rejects_invalid_expression(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI template');
        $this->validator->validate('{invalid name}');
    }

    #[Test]
    public function uri_template_validator_rejects_excessive_expression_count(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('URI template exceeds maximum expression count');
        $this->validator->validate(str_repeat('{x}', 1001));
    }

    #[Test]
    public function uri_template_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate('{unclosed');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('uri-template', $exception->format);
    }

    #[Test]
    public function uri_template_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(['array']);
    }

    public static function suiteConformanceProvider(): array
    {
        return [
            'colon prefix modifier is valid' => ['http://example.com/dictionary/{term:1}/{term}', true],
            'relative colon prefix modifier is valid' => ['dictionary/{term:1}/{term}', true],
            'four-digit prefix max-length is valid' => ['{v:1000}', true],
            'variable name starting with percent-encoded triplet is valid' => ['{%41}', true],
            'explode modifier is valid' => ['{var*}', true],
            'apostrophe in a literal is valid' => ["a'b", true],
            'reserved expansion operator is valid' => ['{+var}', true],
            'empty varspec inside the variable list is invalid' => ['{a,,b}', false],
            'lone percent sign literal is invalid' => ['a%', false],
            'incomplete percent-encoded triplet literal is invalid' => ['a%4', false],
            'non-hex percent-encoding literal is invalid' => ['a%GG', false],
            'trailing dot in a variable name is invalid' => ['{a.}', false],
            'double dot in a variable name is invalid' => ['{a..b}', false],
            'space in a literal is invalid' => ['a b', false],
            'delete character in a literal is invalid' => ["a\x7F" . 'b', false],
            'trailing comma in the variable list is invalid' => ['{a,}', false],
            'default value in a varspec is invalid' => ['{var=def}', false],
            'zero prefix max-length is invalid' => ['{v:0}', false],
            'five-digit prefix max-length is invalid' => ['{v:10000}', false],
            'leading zero in the prefix max-length is invalid' => ['{v:01}', false],
            'combined prefix and explode modifiers are invalid' => ['{var:1*}', false],
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
