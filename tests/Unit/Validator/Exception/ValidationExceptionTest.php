<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationException::class)]
class ValidationExceptionTest extends TestCase
{
    #[Test]
    public function errors_accept_invalid_format_exception(): void
    {
        $formatError = new InvalidFormatException(
            format: 'email',
            value: 'not-email',
            message: 'Invalid email format',
        );

        $exception = new ValidationException(
            message: 'Validation failed',
            errors: [$formatError],
        );

        $errors = $exception->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(ValidationErrorInterface::class, $errors[0]);
        self::assertSame('format', $errors[0]->keyword());
    }

    #[Test]
    public function errors_accept_mixed_error_types(): void
    {
        $formatError = new InvalidFormatException(
            format: 'uri',
            value: 'not-a-uri',
            message: 'Invalid URI',
        );

        $exception = new ValidationException(
            message: 'Multiple validation errors',
            errors: [$formatError],
        );

        $errors = $exception->getErrors();

        self::assertCount(1, $errors);

        foreach ($errors as $error) {
            self::assertInstanceOf(ValidationErrorInterface::class, $error);
        }
    }

    #[Test]
    public function get_errors_returns_typed_array(): void
    {
        $exception = new ValidationException(
            message: 'Test',
            errors: [],
        );

        $errors = $exception->getErrors();

        self::assertIsArray($errors);
        self::assertCount(0, $errors);
    }
}
