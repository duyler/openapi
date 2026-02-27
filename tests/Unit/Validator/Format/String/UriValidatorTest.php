<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UriValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UriValidatorTest extends TestCase
{
    private UriValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UriValidator();
    }

    #[Test]
    public function valid_http_url(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com');
        $this->validator->validate('http://example.com/path');
    }

    #[Test]
    public function valid_https_url(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://example.com');
        $this->validator->validate('https://example.com/path?query=value');
    }

    #[Test]
    public function valid_ftp_url(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('ftp://example.com/file');
    }

    #[Test]
    public function throw_error_for_invalid_url(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid URI format');
        $this->validator->validate('not-a-url');
    }

    #[Test]
    public function throw_error_for_missing_scheme(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('example.com');
    }

    #[Test]
    public function validate_with_query(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com?key=value');
    }

    #[Test]
    public function validate_with_fragment(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com#section');
    }

    #[Test]
    public function validate_with_port(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://example.com:8080');
        $this->validator->validate('https://example.com:8443/path');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
