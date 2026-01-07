<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContainsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ContainsValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ContainsValidator($this->pool);
    }

    #[Test]
    public function validate_when_contains_element(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->validator->validate([1, 2, 3, 15, 4], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_when_no_element_matches(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([1, 2, 3, 4, 5], $schema);
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $containsSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_associative_array(): void
    {
        $containsSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->validator->validate(['key' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_contains_is_null(): void
    {
        $schema = new Schema(type: 'array');

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_with_string_contains(): void
    {
        $containsSchema = new Schema(type: 'string', minLength: 5);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->validator->validate(['a', 'ab', 'abcde', 'xyz'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_no_matching_string(): void
    {
        $containsSchema = new Schema(type: 'string', minLength: 5);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['a', 'ab', 'abc'], $schema);
    }

    #[Test]
    public function validate_first_matching_element(): void
    {
        $containsSchema = new Schema(type: 'number', multipleOf: 5);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->validator->validate([1, 2, 3, 5, 7, 9], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_array_with_optional_contains(): void
    {
        $containsSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate([], $schema);
    }

    #[Test]
    public function validate_multiple_matches(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 5);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->validator->validate([1, 2, 5, 6, 7, 10], $schema);

        $this->expectNotToPerformAssertions();
    }
}
