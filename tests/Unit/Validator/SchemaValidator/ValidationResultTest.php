<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\SchemaValidator\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends TestCase
{
    #[Test]
    public function create_result_with_valid_data(): void
    {
        $result = new ValidationResult(1, []);

        $this->assertSame(1, $result->validCount);
        $this->assertSame([], $result->errors);
        $this->assertSame(0, $result->failedCount);
    }

    #[Test]
    public function create_result_with_errors(): void
    {
        $error = $this->createStub(AbstractValidationError::class);
        $result = new ValidationResult(0, [$error], 1);

        $this->assertSame(0, $result->validCount);
        $this->assertCount(1, $result->errors);
        $this->assertSame($error, $result->errors[0]);
        $this->assertSame(1, $result->failedCount);
    }

    #[Test]
    public function properties_are_readonly(): void
    {
        $result = new ValidationResult(5, []);

        $this->assertSame(5, $result->validCount);
    }

    #[Test]
    public function create_result_with_multiple_errors(): void
    {
        $error1 = $this->createStub(AbstractValidationError::class);
        $error2 = $this->createStub(AbstractValidationError::class);

        $result = new ValidationResult(1, [$error1, $error2], 2);

        $this->assertSame(1, $result->validCount);
        $this->assertCount(2, $result->errors);
        $this->assertSame(2, $result->failedCount);
    }

    #[Test]
    public function failed_count_defaults_to_zero(): void
    {
        $error = $this->createStub(AbstractValidationError::class);
        $result = new ValidationResult(0, [$error]);

        $this->assertSame(0, $result->failedCount);
    }
}
