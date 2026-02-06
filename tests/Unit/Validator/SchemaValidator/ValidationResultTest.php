<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\SchemaValidator\ValidationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ValidationResultTest extends TestCase
{
    #[Test]
    public function create_result_with_valid_data(): void
    {
        $result = new ValidationResult(1, [], []);

        $this->assertSame(1, $result->validCount);
        $this->assertSame([], $result->errors);
        $this->assertSame([], $result->abstractErrors);
    }

    #[Test]
    public function create_result_with_errors(): void
    {
        $error = new ValidationException('Test error');
        $result = new ValidationResult(0, [$error], []);

        $this->assertSame(0, $result->validCount);
        $this->assertCount(1, $result->errors);
        $this->assertSame($error, $result->errors[0]);
        $this->assertSame([], $result->abstractErrors);
    }

    #[Test]
    public function create_result_with_abstract_errors(): void
    {
        $abstractError = $this->createMock(AbstractValidationError::class);
        $result = new ValidationResult(0, [], [$abstractError]);

        $this->assertSame(0, $result->validCount);
        $this->assertSame([], $result->errors);
        $this->assertCount(1, $result->abstractErrors);
        $this->assertSame($abstractError, $result->abstractErrors[0]);
    }

    #[Test]
    public function properties_are_readonly(): void
    {
        $result = new ValidationResult(5, [], []);

        $this->assertSame(5, $result->validCount);
    }

    #[Test]
    public function create_result_with_multiple_errors_and_abstract_errors(): void
    {
        $error1 = new ValidationException('Error 1');
        $error2 = new ValidationException('Error 2');
        $abstractError1 = $this->createMock(AbstractValidationError::class);
        $abstractError2 = $this->createMock(AbstractValidationError::class);

        $result = new ValidationResult(
            1,
            [$error1, $error2],
            [$abstractError1, $abstractError2],
        );

        $this->assertSame(1, $result->validCount);
        $this->assertCount(2, $result->errors);
        $this->assertCount(2, $result->abstractErrors);
    }
}
