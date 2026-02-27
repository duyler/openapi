<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SimpleFormatterTest extends TestCase
{
    private SimpleFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SimpleFormatter();
    }

    #[Test]
    public function format_simple_error(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringContainsString('Value length 2 is less than minimum 3', $formatted);
        $this->assertStringNotContainsString('[/]', $formatted);
    }

    #[Test]
    public function format_error_with_breadcrumb(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/users/0/name',
            schemaPath: '/schema/properties/name/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringStartsWith('[/users/0/name]', $formatted);
        $this->assertStringContainsString('Value length 2 is less than minimum 3', $formatted);
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

        $error2 = new MinLengthError(
            minLength: 5,
            actualLength: 3,
            dataPath: '/users/1/email',
            schemaPath: '/schema/properties/email/minLength',
        );

        $formatted = $this->formatter->formatMultiple([$error1, $error2]);

        $lines = explode("\n", $formatted);
        $this->assertCount(2, $lines);
        $this->assertStringContainsString('/users/0/name', $lines[0]);
        $this->assertStringContainsString('/users/1/email', $lines[1]);
    }
}
