<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\IriValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function suiteConformanceProvider(): array
    {
        return [
            'non-hex percent-encoding is rejected' => ['http://ƒøø.ßår/%6G', false],
            'incomplete percent-encoding triplet is rejected' => ['http://ƒøø.ßår/%A', false],
            'lone percent sign is rejected' => ['http://ƒøø.ßår/%', false],
            'ipv6 host without brackets is rejected' => ['http://2001:0db8:85a3:0000:0000:8a2e:0370:7334', false],
            'leading zero in embedded ipv4 is rejected' => ['http://[::ffff:192.168.0.01]', false],
            'trailing newline is rejected' => ["http://ƒøø.ßår/\n", false],
            'unicode host and path is valid' => ['http://ƒøø.ßår/', true],
            'supplementary-plane private-use char in query is valid' => ["http://ƒøø.ßår/?q=\u{F0000}", true],
            'query and fragment with ucschar are valid' => ['http://ƒøø.ßår/?∂éœ=πîx#πîüx', true],
            'parentheses in path with unicode are valid' => ['http://ƒøø.com/blah_(wîkïpédiå)_blah#ßité-1', true],
            'percent-encoded space in query is valid' => ['http://ƒøø.ßår/?q=Test%20URL-encoded%20stuff', true],
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
