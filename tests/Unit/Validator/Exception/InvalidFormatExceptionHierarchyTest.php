<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(InvalidFormatException::class)]
final class InvalidFormatExceptionHierarchyTest extends TestCase
{
    #[Test]
    public function extends_abstract_validation_error(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertInstanceOf(AbstractValidationError::class, $exception);
    }

    #[Test]
    public function implements_validation_error_interface(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertInstanceOf(ValidationErrorInterface::class, $exception);
    }

    #[Test]
    public function keyword_returns_format(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertSame('format', $exception->keyword());
    }

    #[Test]
    public function data_path_returns_empty_string(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertSame('', $exception->dataPath());
    }

    #[Test]
    public function schema_path_returns_format_path(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertSame('/format', $exception->schemaPath());
    }

    #[Test]
    public function message_returns_exception_message(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertSame('Invalid email format', $exception->message());
    }

    #[Test]
    public function params_expose_format_without_value(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        $params = $exception->params();

        self::assertSame('email', $params['format']);
        self::assertArrayNotHasKey('value', $params);
    }

    #[Test]
    public function suggestion_returns_null_by_default(): void
    {
        $exception = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Invalid email format',
        );

        self::assertNull($exception->suggestion());
    }

    #[Test]
    public function public_readonly_properties_preserved(): void
    {
        $exception = new InvalidFormatException(
            format: 'uuid',
            value: 42,
            message: 'Invalid uuid format',
        );

        self::assertSame('uuid', $exception->format);
        self::assertSame(42, $exception->value(reveal: true));
    }
}
