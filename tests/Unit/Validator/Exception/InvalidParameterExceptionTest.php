<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class InvalidParameterExceptionTest extends TestCase
{
    private const string ATTACKER_PARAMETER_NAME = 'user_id';

    private const string ATTACKER_MESSAGE = 'Invalid value: <script>alert(1)</script>';

    #[Test]
    public function get_message_returns_generic_string_without_attacker_value(): void
    {
        $exception = new InvalidParameterException(self::ATTACKER_PARAMETER_NAME, self::ATTACKER_MESSAGE);

        self::assertSame('Invalid parameter configuration', $exception->getMessage());
        self::assertStringNotContainsString('<script>', $exception->getMessage());
        self::assertStringNotContainsString('alert', $exception->getMessage());
        self::assertStringNotContainsString(self::ATTACKER_PARAMETER_NAME, $exception->getMessage());
        self::assertStringNotContainsString(self::ATTACKER_MESSAGE, $exception->getMessage());
    }

    #[Test]
    public function to_string_is_sanitized_via_trait(): void
    {
        $exception = new InvalidParameterException(self::ATTACKER_PARAMETER_NAME, self::ATTACKER_MESSAGE);

        self::assertSame($exception->getMessage(), (string) $exception);
        self::assertStringNotContainsString('<script>', (string) $exception);
        self::assertStringNotContainsString(self::ATTACKER_PARAMETER_NAME, (string) $exception);
    }

    #[Test]
    public function get_message_is_invariant_regardless_of_attacker_input(): void
    {
        $one = new InvalidParameterException('alpha', 'first message');
        $two = new InvalidParameterException('beta', 'second message');

        self::assertSame($one->getMessage(), $two->getMessage());
    }

    #[Test]
    public function parameter_name_getter_redacts_by_default(): void
    {
        $exception = new InvalidParameterException(self::ATTACKER_PARAMETER_NAME, self::ATTACKER_MESSAGE);

        self::assertSame('<redacted>', $exception->parameterName());
    }

    #[Test]
    public function parameter_name_getter_reveals_value_with_opt_in(): void
    {
        $exception = new InvalidParameterException(self::ATTACKER_PARAMETER_NAME, self::ATTACKER_MESSAGE);

        self::assertSame(self::ATTACKER_PARAMETER_NAME, $exception->parameterName(reveal: true));
    }

    #[Test]
    public function creates_exception_with_code_and_previous(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new InvalidParameterException('param', 'Error', 500, $previous);

        self::assertSame(500, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function invalid_configuration_factory_returns_generic_message(): void
    {
        $exception = InvalidParameterException::invalidConfiguration(
            self::ATTACKER_PARAMETER_NAME,
            self::ATTACKER_MESSAGE,
        );

        self::assertSame('Invalid parameter configuration', $exception->getMessage());
        self::assertStringNotContainsString('<script>', $exception->getMessage());
        self::assertSame(self::ATTACKER_PARAMETER_NAME, $exception->parameterName(reveal: true));
    }

    #[Test]
    public function malformed_value_factory_returns_generic_message(): void
    {
        $exception = InvalidParameterException::malformedValue(
            self::ATTACKER_PARAMETER_NAME,
            self::ATTACKER_MESSAGE,
        );

        self::assertSame('Invalid parameter configuration', $exception->getMessage());
        self::assertStringNotContainsString('<script>', $exception->getMessage());
        self::assertStringNotContainsString('Malformed value', $exception->getMessage());
        self::assertSame(self::ATTACKER_PARAMETER_NAME, $exception->parameterName(reveal: true));
    }

    #[Test]
    public function extends_runtime_exception(): void
    {
        $exception = new InvalidParameterException('param', 'message');

        self::assertInstanceOf(RuntimeException::class, $exception);
    }
}
