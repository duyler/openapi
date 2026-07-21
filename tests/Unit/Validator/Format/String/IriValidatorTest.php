<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IriValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IriValidator::class)]
final class IriValidatorTest extends TestCase
{
    private IriValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IriValidator();
    }

    #[Test]
    public function iri_validator_accepts_unicode_uri(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://例え.テスト/path');
        $this->validator->validate('https://привет.рф/path');
        $this->validator->validate('http://北京.cn/path?q=1');
    }

    #[Test]
    public function iri_validator_accepts_ascii_uri(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('https://example.com');
        $this->validator->validate('http://example.com/path?query=value');
        $this->validator->validate('urn:uuid:550e8400-e29b-41d4-a716-446655440000');
    }

    #[Test]
    public function iri_validator_rejects_relative_ref(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('//example.com/path');
    }

    #[Test]
    public function iri_validator_rejects_invalid(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('not an IRI at all');
    }

    #[Test]
    public function iri_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate('invalid');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('iri', $exception->format);
    }

    #[Test]
    public function iri_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(3.14);
    }
}
