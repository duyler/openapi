<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error\Formatter;

use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DetailedFormatterTest extends TestCase
{
    private DetailedFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new DetailedFormatter();
    }

    #[Test]
    public function format_with_details(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/users/0/name',
            schemaPath: '/schema/properties/name/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringContainsString('Error at /users/0/name', $formatted);
        $this->assertStringContainsString('Message:', $formatted);
        $this->assertStringContainsString('Details:', $formatted);
        $this->assertStringContainsString('minLength:', $formatted);
        $this->assertStringContainsString('actual:', $formatted);
        $this->assertStringContainsString('Suggestion:', $formatted);
    }

    #[Test]
    public function include_suggestions(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/name',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringContainsString('Suggestion: Ensure value has at least 3 characters', $formatted);
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

        $this->assertStringContainsString('Error at /users/0/name', $formatted);
        $this->assertStringContainsString('Error at /users/1/age', $formatted);
        $this->assertStringContainsString("\n\n", $formatted);
    }

    #[Test]
    public function format_root_error(): void
    {
        $error = new MinLengthError(
            minLength: 3,
            actualLength: 2,
            dataPath: '/',
            schemaPath: '/schema/minLength',
        );

        $formatted = $this->formatter->format($error);

        $this->assertStringStartsWith('Error at /', $formatted);
    }
}
