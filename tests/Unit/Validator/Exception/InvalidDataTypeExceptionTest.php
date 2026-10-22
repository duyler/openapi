<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

/** @internal */
final class InvalidDataTypeExceptionTest extends TestCase
{
    private InvalidDataTypeException $exception;

    protected function setUp(): void
    {
        $this->exception = new InvalidDataTypeException(
            message: 'Expected string, got integer',
            code: 422,
        );
    }

    #[Test]
    public function keyword_returns_invalid(): void
    {
        self::assertSame('invalid', $this->exception->keyword());
    }

    #[Test]
    public function data_path_returns_empty_string(): void
    {
        self::assertSame('', $this->exception->dataPath());
    }

    #[Test]
    public function schema_path_returns_empty_string(): void
    {
        self::assertSame('', $this->exception->schemaPath());
    }

    #[Test]
    public function message_returns_exception_message(): void
    {
        self::assertSame('Expected string, got integer', $this->exception->message());
    }

    #[Test]
    public function params_returns_empty_array(): void
    {
        self::assertSame([], $this->exception->params());
    }

    #[Test]
    public function suggestion_returns_null(): void
    {
        self::assertNull($this->exception->suggestion());
    }

    #[Test]
    public function inherits_invalid_argument_exception(): void
    {
        self::assertInstanceOf(InvalidArgumentException::class, $this->exception);
    }
}
