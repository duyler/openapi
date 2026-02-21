<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @internal */
final class InvalidParameterExceptionTest extends TestCase
{
    #[Test]
    public function creates_exception_with_parameter_name(): void
    {
        $exception = new InvalidParameterException('filter', 'Invalid configuration');

        $this->assertSame('filter', $exception->parameterName);
        $this->assertStringContainsString('filter', $exception->getMessage());
        $this->assertStringContainsString('Invalid configuration', $exception->getMessage());
    }

    #[Test]
    public function creates_exception_with_empty_message(): void
    {
        $exception = new InvalidParameterException('test');

        $this->assertSame('test', $exception->parameterName);
    }

    #[Test]
    public function creates_exception_with_code_and_previous(): void
    {
        $previous = new RuntimeException('Previous error');
        $exception = new InvalidParameterException('param', 'Error', 500, $previous);

        $this->assertSame('param', $exception->parameterName);
        $this->assertSame(500, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    #[Test]
    public function creates_invalid_configuration_exception(): void
    {
        $exception = InvalidParameterException::invalidConfiguration('filter', 'Invalid schema');

        $this->assertSame('filter', $exception->parameterName);
        $this->assertStringContainsString('Invalid schema', $exception->getMessage());
    }

    #[Test]
    public function creates_malformed_value_exception(): void
    {
        $exception = InvalidParameterException::malformedValue('filter', 'Invalid JSON syntax');

        $this->assertSame('filter', $exception->parameterName);
        $this->assertStringContainsString('Malformed value', $exception->getMessage());
        $this->assertStringContainsString('Invalid JSON syntax', $exception->getMessage());
    }
}
