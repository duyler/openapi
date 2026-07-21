<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IriReferenceValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(IriReferenceValidator::class)]
final class IriReferenceValidatorTest extends TestCase
{
    private IriReferenceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new IriReferenceValidator();
    }

    #[Test]
    public function iri_reference_validator_accepts_relative_unicode_path(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('//例え.テスト/path');
        $this->validator->validate('/привет/world');
        $this->validator->validate('?query=значение');
    }

    #[Test]
    public function iri_reference_validator_accepts_absolute_iri(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('http://例え.テスト/path');
        $this->validator->validate('https://example.com');
    }

    #[Test]
    public function iri_reference_validator_accepts_relative_ascii_ref(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('/path');
        $this->validator->validate('?q=1');
        $this->validator->validate('#fragment');
        $this->validator->validate('');
    }

    #[Test]
    public function iri_reference_validator_rejects_input_with_whitespace(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('with space');
    }

    #[Test]
    public function iri_reference_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate("with\nnewline");
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('iri-reference', $exception->format);
    }

    #[Test]
    public function iri_reference_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(true);
    }
}
