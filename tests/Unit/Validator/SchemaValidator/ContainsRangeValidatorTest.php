<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\ContainsRangeValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function sprintf;

#[CoversClass(ContainsRangeValidator::class)]
class ContainsRangeValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ContainsRangeValidator $validator;

    #[Override]
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

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 15, 20, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 15, 20, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 10, 15, 20, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate('string value', $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_no_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            minContains: 1,
        );

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_only_min_contains_without_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            minContains: 2,
        );

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_only_max_contains_without_contains(): void
    {
        $schema = new Schema(
            type: 'array',
            maxContains: 2,
        );

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate([1, 2, 3, 4, 5], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['hello', 123, 'world', 456], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['not-numeric', '123', 'also-not'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
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

        $succeeded = false;

        try {
            $this->validator->validate(['ab', 'hello', 42, 'world', 'x'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function regression_catches_validation_exception_when_matching_contains_schema(): void
    {
        $containsSchema = new Schema(type: 'object', required: ['id']);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
            maxContains: 2,
        );

        try {
            $this->validator->validate(
                [['id' => 1], ['name' => 'without-id'], ['id' => 2]],
                $schema,
            );
        } catch (Throwable $e) {
            self::fail(sprintf(
                'Expected validation to pass after catching ValidationException, got %s: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::assertTrue(true);
    }

    #[Test]
    public function regression_throws_min_contains_error_when_validation_exception_reduces_match_count(): void
    {
        $containsSchema = new Schema(type: 'object', required: ['id']);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 3,
        );

        try {
            $this->validator->validate(
                [['id' => 1], ['name' => 'without-id'], ['id' => 2]],
                $schema,
            );
            self::fail('Expected MinContainsError was not thrown');
        } catch (MinContainsError $e) {
            self::assertSame('minContains', $e->keyword());
            self::assertSame('/', $e->dataPath());
            self::assertSame(['minContains' => 3, 'actual' => 2], $e->params());
        }
    }

    #[Test]
    public function validate_max_contains_zero_when_no_item_matches(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 100);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 0,
        );

        try {
            $this->validator->validate([1, 2, 3], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf(
                'Expected validation to pass with maxContains=0 and no matching items, got %s: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::assertTrue(true);
    }

    #[Test]
    public function throw_max_contains_error_when_max_contains_zero_and_item_matches(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 0,
        );

        try {
            $this->validator->validate([1, 2, 15, 3], $schema);
            self::fail('Expected MaxContainsError was not thrown');
        } catch (MaxContainsError $e) {
            self::assertSame('maxContains', $e->keyword());
            self::assertSame('/', $e->dataPath());
            self::assertSame(['maxContains' => 0, 'actual' => 1], $e->params());
        }
    }
}
