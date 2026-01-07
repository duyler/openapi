<?php

declare(strict_types=1);

namespace Tests\Duyler\OpenApi\Validator\Format\String;

use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Format\String\UuidValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidValidatorTest extends TestCase
{
    private UuidValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new UuidValidator();
    }

    #[Test]
    public function valid_uuid_v4(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123e4567-e89b-12d3-a456-426614174000');
    }

    #[Test]
    public function valid_uuid_v1(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('f47ac10b-58cc-4372-a567-0e02b2c3d479');
    }

    #[Test]
    public function throw_error_for_invalid_format(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->expectExceptionMessage('Invalid UUID format');
        $this->validator->validate('not-a-uuid');
    }

    #[Test]
    public function throw_error_for_invalid_hex(): void
    {
        $this->expectException(InvalidFormatException::class);
        $this->validator->validate('123e4567-e89b-12d3-g456-426614174000');
    }

    #[Test]
    public function validate_uppercase_uuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123E4567-E89B-12D3-A456-426614174000');
    }

    #[Test]
    public function validate_lowercase_uuid(): void
    {
        $this->expectNotToPerformAssertions();
        $this->validator->validate('123e4567-e89b-12d3-a456-426614174000');
    }
}
