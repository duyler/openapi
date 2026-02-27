<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use ValueError;

use const JSON_ERROR_NONE;
use const INF;
use const NAN;

class JsonFormatterTest extends TestCase
{
    private JsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
    }

    #[Test]
    public function format_single_error(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/users/0/name',
            schemaPath: '/schema/properties/name/minLength',
        );

        $formatted = $this->formatter->format($error);
        $decoded = json_decode($formatted, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('breadcrumb', $decoded);
        $this->assertArrayHasKey('message', $decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertArrayHasKey('details', $decoded);
        $this->assertArrayHasKey('suggestion', $decoded);

        $this->assertSame('/users/0/name', $decoded['breadcrumb']);
        $this->assertSame('minLength', $decoded['type']);
        $this->assertArrayHasKey('minLength', $decoded['details']);
        $this->assertArrayHasKey('actual', $decoded['details']);
    }

    #[Test]
    public function format_multiple_errors(): void
    {
        $error1 = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/users/0/name',
            schemaPath: '/schema/properties/name/minLength',
        );

        $error2 = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/users/1/age',
            schemaPath: '/schema/properties/age/type',
        );

        $formatted = $this->formatter->formatMultiple([$error1, $error2]);
        $decoded = json_decode($formatted, true);

        $this->assertArrayHasKey('errors', $decoded);
        $this->assertArrayHasKey('count', $decoded);
        $this->assertCount(2, $decoded['errors']);
        $this->assertSame(2, $decoded['count']);
    }

    #[Test]
    public function produce_valid_json(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/name',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertNotEmpty($formatted);
        $decoded = json_decode($formatted, true);
        $this->assertIsArray($decoded);
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }

    #[Test]
    public function include_pretty_print(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/name',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        // Pretty printed JSON should contain newlines
        $this->assertStringContainsString("\n", $formatted);
    }

    #[Test]
    public function error_without_suggestion(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'int',
            dataPath: '/age',
            schemaPath: '/schema/type',
            suggestion: null,
        );

        $formatted = $this->formatter->format($error);
        $decoded = json_decode($formatted, true);

        // TypeMismatchError should still have a default suggestion
        $this->assertArrayHasKey('suggestion', $decoded);
    }

    #[Test]
    public function format_throws_value_error_for_encode_failure(): void
    {
        $error = new class ('/test', 'test') extends AbstractValidationError {
            public function __construct(string $dataPath, string $schemaPath)
            {
                parent::__construct(
                    message: 'Test error',
                    keyword: 'test',
                    dataPath: $dataPath,
                    schemaPath: $schemaPath,
                    params: ['key' => INF],
                );
            }

            public function getType(): string
            {
                return 'test';
            }
        };

        $this->expectException(ValueError::class);
        $this->expectExceptionMessage('Failed to encode error data to JSON');

        $this->formatter->format($error);
    }

    #[Test]
    public function format_multiple_throws_value_error_for_encode_failure(): void
    {
        $error = new class ('/test', 'test') extends AbstractValidationError {
            public function __construct(string $dataPath, string $schemaPath)
            {
                parent::__construct(
                    message: 'Test error',
                    keyword: 'test',
                    dataPath: $dataPath,
                    schemaPath: $schemaPath,
                    params: ['key' => NAN],
                );
            }

            public function getType(): string
            {
                return 'test';
            }
        };

        $this->expectException(ValueError::class);

        $this->formatter->formatMultiple([$error]);
    }
}
