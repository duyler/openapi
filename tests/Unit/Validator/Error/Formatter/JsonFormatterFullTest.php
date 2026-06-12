<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class JsonFormatterFullTest extends TestCase
{
    private JsonFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new JsonFormatter();
    }

    #[Test]
    public function format_single_error(): void
    {
        $error = new TypeMismatchError(
            expected: 'string',
            actual: 'integer',
            dataPath: '/name',
            schemaPath: '/type',
        );

        $result = $this->formatter->format($error);

        $decoded = json_decode($result, true);
        $this->assertSame('/name', $decoded['breadcrumb']);
        $this->assertSame('string', $decoded['details']['expected']);
        $this->assertSame('integer', $decoded['details']['actual']);
    }

    #[Test]
    public function format_error_with_suggestion(): void
    {
        $error = new MinimumError(
            minimum: 0,
            actual: -1,
            dataPath: '/age',
            schemaPath: '/minimum',
        );

        $result = $this->formatter->format($error);

        $decoded = json_decode($result, true);
        $this->assertSame('/age', $decoded['breadcrumb']);
        $this->assertArrayHasKey('suggestion', $decoded);
    }

    #[Test]
    public function format_multiple_errors(): void
    {
        $errors = [
            new TypeMismatchError('string', 'int', '/name', '/type'),
            new TypeMismatchError('integer', 'string', '/age', '/type'),
        ];

        $result = $this->formatter->formatMultiple($errors);

        $decoded = json_decode($result, true);
        $this->assertCount(2, $decoded['errors']);
        $this->assertSame(2, $decoded['count']);
    }
}
