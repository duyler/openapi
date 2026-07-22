<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\RegexFormatValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function bin2hex;
use function random_bytes;

#[CoversClass(RegexFormatValidator::class)]
final class RegexFormatValidatorTest extends TestCase
{
    private RegexFormatValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new RegexFormatValidator();
    }

    #[Test]
    public function regex_validator_accepts_valid_pattern(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('^[a-z]+$');
        $this->validator->validate('\d{4}');
        $this->validator->validate('(?P<name>\w+)');
        $this->validator->validate('[A-Z]+');
        $this->validator->validate('.+');
        $this->validator->validate('foo|bar');
        $this->validator->validate('');
    }

    #[Test]
    public function regex_validator_accepts_complex_pattern(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('^(?:https?|ftp)://[^\s/$.?#].[^\s]*$');
        $this->validator->validate('(?=.*[A-Z])(?=.*[a-z])(?=.*\d).{8,}$');
    }

    #[Test]
    public function regex_validator_rejects_unterminated_class(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid regular expression pattern');
        $this->validator->validate('[invalid');
    }

    #[Test]
    public function regex_validator_rejects_unbalanced_group(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('(?P<name');
    }

    #[Test]
    public function regex_validator_rejects_invalid_quantifier(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('*');
    }

    #[Test]
    public function regex_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            $this->validator->validate('[invalid');
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('regex', $exception->format);
    }

    #[Test]
    public function regex_validator_does_not_disclose_pattern_in_message(): void
    {
        $attackerPattern = 'attacker_pattern_' . bin2hex(random_bytes(8));

        $exception = null;

        try {
            $this->validator->validate('[' . $attackerPattern);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertStringNotContainsString($attackerPattern, $exception->getMessage());
    }

    #[Test]
    public function regex_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        $this->validator->validate(123);
    }
}
