<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IriReferenceValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function suiteConformanceProvider(): array
    {
        return [
            'backslash path with unicode is rejected' => ['\\\\WINDOWS\\filëßåré', false],
            'backslash in fragment is rejected' => ['#ƒräg\\mênt', false],
            'leading zero in embedded ipv4 is rejected' => ['//[::ffff:192.168.0.01]/p', false],
            'trailing newline is rejected' => ["/âππ\n", false],
            'non-hex percent-encoding is rejected' => ['/%6G', false],
            'incomplete percent-encoding triplet is rejected' => ['/%A', false],
            'lone percent sign is rejected' => ['/%', false],
            'empty reference is valid' => ['', true],
            'unicode relative path is valid' => ['/âππ', true],
            'unicode fragment is valid' => ['#ƒrägmênt', true],
            'protocol-relative unicode with query and fragment is valid' => ['//ƒøø.ßår/?∂éœ=πîx#πîüx', true],
            'absolute unicode iri is valid' => ['http://ƒøø.ßår/?∂éœ=πîx#πîüx', true],
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
