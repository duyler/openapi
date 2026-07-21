<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UriReferenceValidator;
use PHPUnit\Framework\Attributes\CoversClass;
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
}
