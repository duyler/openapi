<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\ContainsRangeValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ContainsRangeValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ContainsRangeValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ContainsRangeValidator($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function validate_min_contains(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
        );

        $this->validator->validate([1, 2, 15, 20, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_max_contains(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 2,
        );

        $this->validator->validate([1, 2, 15, 20, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_min_contains_error(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 3,
        );

        $this->expectException(MinContainsError::class);

        $this->validator->validate([1, 2, 15, 20, 3], $schema);
    }

    #[Test]
    public function throw_max_contains_error(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 1,
        );

        $this->expectException(MaxContainsError::class);

        $this->validator->validate([1, 2, 15, 20, 3], $schema);
    }

    #[Test]
    public function validate_both_min_and_max(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
            maxContains: 3,
        );

        $this->validator->validate([1, 2, 10, 15, 20, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_non_array(): void
    {
        $containsSchema = new Schema(type: 'number');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 1,
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_no_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            minContains: 1,
        );

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_only_min_contains_without_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            minContains: 2,
        );

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_only_max_contains_without_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            maxContains: 2,
        );

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_zero_matches_when_min_is_zero(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 100);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 0,
        );

        $this->validator->validate([1, 2, 3, 4, 5], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function catches_abstract_validation_error_on_type_mismatch(): void
    {
        $containsSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
        );

        $this->validator->validate(['hello', 123, 'world', 456], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function catches_abstract_validation_error_on_pattern_mismatch(): void
    {
        $containsSchema = new Schema(type: 'string', pattern: '^\d+$');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 1,
        );

        $this->validator->validate(['not-numeric', '123', 'also-not'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function correctly_counts_matching_items_with_various_error_types(): void
    {
        $containsSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
            maxContains: 3,
        );

        $this->validator->validate(['ab', 'hello', 42, 'world', 'x'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
