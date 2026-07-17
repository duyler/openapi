<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Exception;

use Duyler\OpenApi\Validator\Exception\ValidationErrorInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Throwable;

#[CoversClass(ValidationException::class)]
final class ValidationExceptionSubclassTest extends TestCase
{
    #[Test]
    public function validation_exception_no_longer_final(): void
    {
        $reflection = new ReflectionClass(ValidationException::class);

        self::assertFalse($reflection->isFinal());
    }

    #[Test]
    public function subclass_can_be_thrown_and_caught(): void
    {
        $subclass = new class ('domain failure') extends ValidationException {};

        try {
            throw $subclass;
        } catch (ValidationException $caught) {
            self::assertSame('domain failure', $caught->getMessage());
        }
    }

    #[Test]
    public function subclass_preserves_errors_contract(): void
    {
        $formatError = new class implements ValidationErrorInterface {
            public function keyword(): string
            {
                return 'type';
            }

            public function dataPath(): string
            {
                return '/name';
            }

            public function schemaPath(): string
            {
                return '/properties/name/type';
            }

            public function message(): string
            {
                return 'must be string';
            }

            public function params(): array
            {
                return ['expected' => 'string'];
            }

            public function suggestion(): ?string
            {
                return null;
            }

            public function getType(): string
            {
                return 'type';
            }
        };

        $subclass = new class ('subclass', 0, null, [$formatError]) extends ValidationException {
            /**
             * @param array<int, ValidationErrorInterface> $errors
             */
            public function __construct(
                string $message,
                int $code,
                ?Throwable $previous,
                private readonly array $errors,
            ) {
                parent::__construct($message, $code, $previous);
            }

            /**
             * @return array<int, ValidationErrorInterface>
             */
            public function getErrors(): array
            {
                return $this->errors;
            }
        };

        $errors = $subclass->getErrors();

        self::assertCount(1, $errors);
        self::assertInstanceOf(ValidationErrorInterface::class, $errors[0]);
        self::assertSame('type', $errors[0]->keyword());
    }
}
