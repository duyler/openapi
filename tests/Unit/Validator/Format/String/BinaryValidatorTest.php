<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Format\String;

use Duyler\OpenApi\Validator\Format\String\BinaryValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;

use function random_bytes;

#[CoversClass(BinaryValidator::class)]
final class BinaryValidatorTest extends TestCase
{
    #[Test]
    public function binary_validator_passes_through_simple_string(): void
    {
        $this->expectNotToPerformAssertions();
        new BinaryValidator()->validate('any binary content');
    }

    #[Test]
    public function binary_validator_passes_through_empty_string(): void
    {
        $this->expectNotToPerformAssertions();
        new BinaryValidator()->validate('');
    }

    #[Test]
    public function binary_validator_passes_through_random_bytes(): void
    {
        $this->expectNotToPerformAssertions();
        new BinaryValidator()->validate(random_bytes(1024));
    }

    #[Test]
    public function binary_validator_rejects_non_string(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Value must be a string');
        new BinaryValidator()->validate(42);
    }

    #[Test]
    public function binary_validator_passes_through_base64_string(): void
    {
        $this->expectNotToPerformAssertions();
        new BinaryValidator()->validate('SGVsbG8gV29ybGQ=');
    }
}
