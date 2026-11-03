<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UriReferenceValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(UriReferenceValidator::class)]
final class UriReferenceValidatorTest extends TestCase
{
    private UriReferenceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UriReferenceValidator();
    }

    #[Test]
    public function uri_reference_validator_accepts_relative_ref(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('//example.com/path');
        $this->validator->validate('/path');
        $this->validator->validate('?q=1');
        $this->validator->validate('#frag');
        $this->validator->validate('path/to/resource');
        $this->validator->validate('');
    }

    #[Test]
    public function uri_reference_validator_accepts_absolute_uri(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://example.com/path');
        $this->validator->validate('urn:uuid:550e8400-e29b-41d4-a716-446655440000');
    }

    #[Test]
    public function uri_reference_validator_accepts_network_path_with_userinfo(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('//user@example.com/path');
        $this->validator->validate('//user:p%40ss@example.com:8080/path?q=1#frag');
    }

    #[Test]
    public function uri_reference_validator_rejects_input_with_whitespace(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('with space');
    }

    #[Test]
    public function uri_reference_validator_rejects_input_with_newline(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate("path\nwith newline");
    }

    #[Test]
    public function uri_reference_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate("with\nnewline");
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('uri-reference', $exception->format);
    }

    #[Test]
    public function uri_reference_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(null);
    }

    public static function suiteConformanceProvider(): array
    {
        return [
            'empty reference is valid' => ['', true],
            'backslash path is rejected' => ['\\\\WINDOWS\\fileshare', false],
            'backslash in fragment is rejected' => ['#frag\\ment', false],
            'unescaped non-ascii is rejected' => ["/foobar\u{AE}.txt", false],
            'backslash in absolute path is rejected' => ['https://example.org/foobar\\.txt', false],
            'non-hex percent-encoding is rejected' => ['/%zz', false],
            'incomplete percent-encoding triplet is rejected' => ['/%A', false],
            'lone percent sign is rejected' => ['/%', false],
            'double quote in path is rejected' => ['/a"b', false],
            'square brackets outside authority are rejected' => ['/[::1]', false],
            'non-numeric port is rejected' => ['//example.com:abc/p', false],
            'more than one at-sign in authority is rejected' => ['//a@b@example.com/', false],
            'leading zero in embedded ipv4 is rejected' => ['//[::ffff:192.168.0.01]/p', false],
            'colon in first relative segment is rejected' => ['1:b', false],
            'trailing line feed is rejected' => ["/p\n", false],
            'full uri with query and fragment is valid' => ['http://foo.bar/?baz=qux#quux', true],
            'protocol-relative with query is valid' => ['//foo.bar/?baz=qux#quux', true],
            'fragment-only is valid' => ['#fragment', true],
            'query-only is valid' => ['?query=1', true],
            'percent-encoded userinfo is valid' => ['//user:p%40ss@example.com:8080/path?q=1#frag', true],
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
