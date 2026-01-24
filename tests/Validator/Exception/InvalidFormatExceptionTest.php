<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InvalidFormatExceptionTest extends TestCase
{
    #[Test]
    public function can_create_with_format_and_value(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'invalid-email',
            message: 'Invalid email format',
        );

        self::assertSame('email', $exception->format);
        self::assertSame('invalid-email', $exception->value);
        self::assertSame('Invalid email format', $exception->getMessage());
    }

    #[Test]
    public function keyword_returns_format(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Test message',
        );

        self::assertSame('format', $exception->keyword());
    }

    #[Test]
    public function dataPath_returns_empty_string(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Test message',
        );

        self::assertSame('', $exception->dataPath());
    }

    #[Test]
    public function schemaPath_returns_format_path(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Test message',
        );

        self::assertSame('/format', $exception->schemaPath());
    }

    #[Test]
    public function message_returns_exception_message(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Invalid email format',
        );

        self::assertSame('Invalid email format', $exception->message());
    }

    #[Test]
    public function params_returns_format_and_value(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Test message',
        );

        $params = $exception->params();

        self::assertIsArray($params);
        self::assertArrayHasKey('format', $params);
        self::assertArrayHasKey('value', $params);
        self::assertSame('email', $params['format']);
        self::assertSame('test@example.com', $params['value']);
    }

    #[Test]
    public function suggestion_returns_null(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Test message',
        );

        self::assertNull($exception->suggestion());
    }

    #[Test]
    public function getType_returns_format(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'test@example.com',
            message: 'Test message',
        );

        self::assertSame('format', $exception->getType());
    }
}
