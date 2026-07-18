<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FormatExceptionTest extends TestCase
{
    private ValidationException $exception;

    protected function setUp(): void
    {
        $error = new InvalidFormatException(
            format: 'email',
            value: 'not-an-email',
            message: 'Value must be a valid email address',
        );

        $this->exception = new ValidationException(
            message: 'Validation failed',
            errors: [$error],
        );
    }

    #[Test]
    public function simple_formatter_formats_exception(): void
    {
        $formatter = new SimpleFormatter();

        $result = $formatter->formatException($this->exception);

        self::assertStringContainsString('Value must be a valid email address', $result);
    }

    #[Test]
    public function detailed_formatter_formats_exception(): void
    {
        $formatter = new DetailedFormatter();

        $result = $formatter->formatException($this->exception);

        self::assertStringContainsString('Value must be a valid email address', $result);
        self::assertStringContainsString('email', $result);
        // Default formatter must NOT expose the raw input value (SEC-06).
        self::assertStringNotContainsString('not-an-email', $result);
    }

    #[Test]
    public function detailed_formatter_with_sensitive_values_includes_value(): void
    {
        $formatter = new DetailedFormatter(includeSensitiveValues: true);

        $result = $formatter->formatException($this->exception);

        self::assertStringContainsString('Value must be a valid email address', $result);
        self::assertStringContainsString('not-an-email', $result);
    }

    #[Test]
    public function json_formatter_formats_exception(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->formatException($this->exception);

        $decoded = json_decode($result, true);

        self::assertIsArray($decoded);
        self::assertArrayHasKey('errors', $decoded);
        self::assertArrayHasKey('count', $decoded);
        self::assertSame(1, $decoded['count']);
        self::assertSame('format', $decoded['errors'][0]['type']);
    }

    #[Test]
    public function format_exception_equivalent_to_get_formatted_errors(): void
    {
        $simple = new SimpleFormatter()->formatException($this->exception);
        $detailed = new DetailedFormatter()->formatException($this->exception);
        $json = new JsonFormatter()->formatException($this->exception);

        $simpleExpected = new SimpleFormatter()->formatMultiple($this->exception->getErrors());
        $detailedExpected = new DetailedFormatter()->formatMultiple($this->exception->getErrors());
        $jsonExpected = new JsonFormatter()->formatMultiple($this->exception->getErrors());

        self::assertSame($simpleExpected, $simple);
        self::assertSame($detailedExpected, $detailed);
        self::assertSame($jsonExpected, $json);
    }

    #[Test]
    public function format_exception_handles_empty_errors(): void
    {
        $emptyException = new ValidationException(message: 'No errors');

        $simple = new SimpleFormatter()->formatException($emptyException);
        $detailed = new DetailedFormatter()->formatException($emptyException);
        $json = new JsonFormatter()->formatException($emptyException);

        self::assertSame('', $simple);
        self::assertSame('', $detailed);
        self::assertStringContainsString('"count": 0', $json);
    }
}
