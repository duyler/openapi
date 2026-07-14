<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function json_decode;

use const JSON_ERROR_NONE;

final class ErrorFormatterTest extends TestCase
{
    /**
     * @return array<string, array{0: ErrorFormatterInterface}>
     */
    public static function provideFormatters(): array
    {
        return [
            'simple' => [new SimpleFormatter()],
            'detailed' => [new DetailedFormatter()],
            'json' => [new JsonFormatter()],
        ];
    }

    #[Test]
    #[DataProvider('provideFormatters')]
    public function format_multiple_with_empty_array_does_not_throw(ErrorFormatterInterface $formatter): void
    {
        $result = $formatter->formatMultiple([]);

        self::assertIsString($result);
    }

    #[Test]
    public function simple_formatter_format_multiple_empty_returns_empty_string(): void
    {
        $formatter = new SimpleFormatter();

        $result = $formatter->formatMultiple([]);

        self::assertSame('', $result);
    }

    #[Test]
    public function detailed_formatter_format_multiple_empty_returns_empty_string(): void
    {
        $formatter = new DetailedFormatter();

        $result = $formatter->formatMultiple([]);

        self::assertSame('', $result);
    }

    #[Test]
    public function json_formatter_format_multiple_empty_returns_valid_json_with_zero_count(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->formatMultiple([]);

        $decoded = json_decode($result, true);

        self::assertIsArray($decoded);
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        self::assertSame([], $decoded['errors']);
        self::assertSame(0, $decoded['count']);
    }

    #[Test]
    public function json_formatter_format_multiple_empty_payload_is_non_empty_string(): void
    {
        $formatter = new JsonFormatter();

        $result = $formatter->formatMultiple([]);

        self::assertNotSame('', $result);
    }

    #[Test]
    public function format_multiple_with_single_error_round_trips_through_all_formatters(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/name',
            schemaPath: '/schema/minLength',
        );

        $simpleResult = new SimpleFormatter()->formatMultiple([$error]);
        $detailedResult = new DetailedFormatter()->formatMultiple([$error]);
        $jsonResult = new JsonFormatter()->formatMultiple([$error]);

        self::assertStringContainsString('/name', $simpleResult);
        self::assertStringContainsString('Error at /name', $detailedResult);

        $decoded = json_decode($jsonResult, true);
        self::assertIsArray($decoded);
        self::assertSame(1, $decoded['count']);
        self::assertCount(1, $decoded['errors']);
        self::assertSame('/name', $decoded['errors'][0]['breadcrumb']);
    }
}
