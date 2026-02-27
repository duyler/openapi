<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StringLengthValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private StringLengthValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new StringLengthValidator($this->pool);
    }

    #[Test]
    public function validate_min_length(): void
    {
        $schema = new Schema(type: 'string', minLength: 3);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_max_length(): void
    {
        $schema = new Schema(type: 'string', maxLength: 10);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_both_min_and_max(): void
    {
        $schema = new Schema(type: 'string', minLength: 3, maxLength: 10);

        $this->validator->validate('hello', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_min_length_error(): void
    {
        $schema = new Schema(type: 'string', minLength: 5);

        $this->expectException(MinLengthError::class);

        $this->validator->validate('hi', $schema);
    }

    #[Test]
    public function throw_max_length_error(): void
    {
        $schema = new Schema(type: 'string', maxLength: 3);

        $this->expectException(MaxLengthError::class);

        $this->validator->validate('hello', $schema);
    }

    #[Test]
    public function skip_validation_for_non_string(): void
    {
        $schema = new Schema(type: 'integer', minLength: 3);

        $this->validator->validate(123, $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_unicode_string_length(): void
    {
        $schema = new Schema(type: 'string', minLength: 3);

        $this->validator->validate('Привет', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_string(): void
    {
        $schema = new Schema(type: 'string', minLength: 0);

        $this->validator->validate('', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_empty_string_when_min_greater_than_zero(): void
    {
        $schema = new Schema(type: 'string', minLength: 1);

        $this->expectException(MinLengthError::class);

        $this->validator->validate('', $schema);
    }

    #[Test]
    public function skip_when_no_length_constraints(): void
    {
        $schema = new Schema(type: 'string');

        $this->validator->validate('any string', $schema);

        $this->expectNotToPerformAssertions();
    }
}
