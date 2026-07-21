<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\PasswordValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PasswordValidator::class)]
final class PasswordValidatorTest extends TestCase
{
    #[Test]
    public function password_validator_passes_through_simple_secret(): void
    {
        $this->expectNotToPerformAssertions();
        new PasswordValidator()->validate('secret123!');
    }

    #[Test]
    public function password_validator_passes_through_empty_string(): void
    {
        $this->expectNotToPerformAssertions();
        new PasswordValidator()->validate('');
    }

    #[Test]
    public function password_validator_passes_through_strong_password(): void
    {
        $this->expectNotToPerformAssertions();
        new PasswordValidator()->validate('CorrectHorseBatteryStaple$42!');
        new PasswordValidator()->validate('P@ssw0rd_with_Unicode_пароль');
    }

    #[Test]
    public function password_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        new PasswordValidator()->validate(['not', 'a', 'string']);
    }

    #[Test]
    public function password_validator_format_name_is_correct(): void
    {
        $exception = null;

        try {
            new PasswordValidator()->validate(false);
        } catch (InvalidFormatException $exception) {
        }

        $this->assertNotNull($exception);
        $this->assertSame('password', $exception->format);
    }
}
