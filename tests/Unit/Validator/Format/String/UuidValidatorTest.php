<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UuidValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversClass(UuidValidator::class)]
final class UuidValidatorTest extends TestCase
{
    private UuidValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UuidValidator();
    }

    #[Test]
    public function valid_uuid_v4(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123e4567-e89b-12d3-a456-426614174000');
    }

    #[Test]
    public function valid_uuid_v1(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('f47ac10b-58cc-4372-a567-0e02b2c3d479');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        $this->validator->validate('not-a-uuid');
    }

    #[Test]
    public function throw_error_for_invalid_hex(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('123e4567-e89b-12d3-g456-426614174000');
    }

    #[Test]
    public function validate_uppercase_uuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123E4567-E89B-12D3-A456-426614174000');
    }

    #[Test]
    public function validate_lowercase_uuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123e4567-e89b-12d3-a456-426614174000');
    }

    #[Test]
    public function validate_mixed_case_uuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123e4567-E89b-12d3-A456-426614174000');
    }

    #[Test]
    public function throw_error_for_invalid_length(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        $this->validator->validate('123e4567-e89b-12d3-a456');
    }

    #[Test]
    public function throw_error_for_invalid_version(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        $this->validator->validate('123e4567-e89b-62d3-a456-426614174000');
    }

    #[Test]
    public function throw_error_for_invalid_variant(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        $this->validator->validate('123e4567-e89b-12d3-c456-426614174000');
    }

    #[Test]
    public function throw_error_for_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }

    /**
     * RFC 4122 UUID versions 1-5 are accepted by the validator regex
     * `[1-5]` for the version nibble and `[89abAB]` for the variant nibble.
     * The nil UUID `00000000-0000-0000-0000-000000000000` (RFC 4122 §4.1.7)
     * has both version and variant nibbles set to `0` and is therefore
     * validated explicitly before applying the standard regex pattern.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function uuidVersionsAndSpecialCasesProvider(): array
    {
        return [
            'v3 UUID is valid (version nibble = 3)' => ['e6b1c000-0000-3000-8000-000000000000', true],
            'v3 UUID with realistic values is valid' => ['6ec0bd7f-11c0-30a0-ab90-1234567890ab', true],
            'v4 UUID is valid (version nibble = 4)' => ['123e4567-e89b-42d3-a456-426614174000', true],
            'v5 UUID is valid (version nibble = 5)' => ['e6b1c000-0000-5000-8000-000000000000', true],
            'v5 UUID with variant b is valid' => ['2e9bff0c-31ff-5c11-9b51-001a7c7f9c10', true],
            'v1 UUID is valid (version nibble = 1)' => ['f47ac10b-58cc-4372-a567-0e02b2c3d479', true],
            'v2 UUID is valid (version nibble = 2)' => ['000003e8-113c-21ef-8500-325096b39f47', true],
            'nil UUID is valid per RFC 4122 §4.1.7' => ['00000000-0000-0000-0000-000000000000', true],
            'non-UUID string is rejected' => ['not-a-uuid', false],
            'UUID with invalid hex character is rejected' => ['123e4567-e89b-12d3-g456-426614174000', false],
            'UUID with version 6 is rejected' => ['123e4567-e89b-62d3-a456-426614174000', false],
            'UUID with version 0 is rejected' => ['123e4567-e89b-02d3-a456-426614174000', false],
            'UUID with invalid variant c is rejected' => ['123e4567-e89b-12d3-c456-426614174000', false],
        ];
    }

    #[DataProvider('uuidVersionsAndSpecialCasesProvider')]
    #[Test]
    public function uuid_versions_and_special_cases_match_expected_result(string $uuid, bool $expectedValid): void
    {
        $exception = null;

        try {
            $this->validator->validate($uuid);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertSame(
            $expectedValid,
            null === $exception,
            sprintf(
                'UUID "%s" was expected to be %s but is %s',
                $uuid,
                $expectedValid ? 'valid' : 'invalid',
                null === $exception ? 'valid' : 'invalid: ' . $exception->getMessage(),
            ),
        );
    }

    #[Test]
    public function nil_uuid_is_valid_per_rfc_4122(): void
    {
        $nilUuid = '00000000-0000-0000-0000-000000000000';

        $exception = null;

        try {
            $this->validator->validate($nilUuid);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNull(
            $exception,
            'Nil UUID should be valid per RFC 4122 §4.1.7: the validator must accept '
            . '00000000-0000-0000-0000-000000000000 as a special UUID with all bits zero.',
        );
    }
}
