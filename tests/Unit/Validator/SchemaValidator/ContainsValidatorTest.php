<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ContainsMatchError;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

use function sprintf;

#[CoversClass(ContainsValidator::class)]
class ContainsValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private ContainsValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new ContainsValidator($this->pool, BuiltinFormats::create());
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

        $this->expectException(ContainsMatchError::class);

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

        $this->expectException(ContainsMatchError::class);

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

        $this->expectException(ContainsMatchError::class);

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

    #[Test]
    public function contains_with_default_min_contains_one_throws_on_zero_matches(): void
    {
        $containsSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
        );

        $this->expectException(ContainsMatchError::class);

        $this->validator->validate(['a', 'b', 'c'], $schema);
    }

    #[Test]
    public function contains_with_min_contains_two_requires_two_matches(): void
    {
        $containsSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
        );

        try {
            $this->validator->validate([1, 'a', 'b'], $schema);
            self::fail('Expected MinContainsError was not thrown');
        } catch (MinContainsError $e) {
            self::assertSame('minContains', $e->keyword());
            self::assertSame('/', $e->dataPath());
            self::assertSame(['minContains' => 2, 'actual' => 1], $e->params());
        }
    }

    #[Test]
    public function contains_with_max_contains_one_rejects_two_matches(): void
    {
        $containsSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 1,
        );

        try {
            $this->validator->validate([1, 2, 'a'], $schema);
            self::fail('Expected MaxContainsError was not thrown');
        } catch (MaxContainsError $e) {
            self::assertSame('maxContains', $e->keyword());
            self::assertSame('/', $e->dataPath());
            self::assertSame(['maxContains' => 1, 'minDetectedCount' => 2], $e->params());
        }
    }

    #[Test]
    public function contains_with_match_in_range_passes(): void
    {
        $containsSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
            maxContains: 3,
        );

        $this->validator->validate([1, 'a', 2, 'b'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_min_contains_passes_when_threshold_met(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
        );

        try {
            $this->validator->validate([1, 2, 15, 20, 3], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
    }

    #[Test]
    public function validate_max_contains_passes_when_within_ceiling(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 2,
        );

        try {
            $this->validator->validate([1, 2, 15, 20, 3], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
    }

    #[Test]
    public function validate_both_min_and_max_in_range(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 10);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 2,
            maxContains: 3,
        );

        try {
            $this->validator->validate([1, 2, 10, 15, 20, 3], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
    }

    #[Test]
    public function skip_when_only_min_contains_without_contains_schema(): void
    {
        $schema = new Schema(
            type: 'array',
            minContains: 2,
        );

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_only_max_contains_without_contains_schema(): void
    {
        $schema = new Schema(
            type: 'array',
            maxContains: 2,
        );

        $this->validator->validate([1, 2, 3], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_zero_matches_when_min_contains_is_zero(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 100);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            minContains: 0,
        );

        try {
            $this->validator->validate([1, 2, 3, 4, 5], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
    }

    #[Test]
    public function throw_contains_match_error_when_max_contains_zero_and_no_item_matches(): void
    {
        $containsSchema = new Schema(type: 'number', minimum: 100);
        $schema = new Schema(
            type: 'array',
            contains: $containsSchema,
            maxContains: 0,
        );

        $this->expectException(ContainsMatchError::class);

        $this->validator->validate([1, 2, 3], $schema);
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
            self::assertSame(['maxContains' => 0, 'minDetectedCount' => 1], $e->params());
        }
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

        try {
            $this->validator->validate(['hello', 123, 'world', 456], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
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

        try {
            $this->validator->validate(['not-numeric', '123', 'also-not'], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
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

        try {
            $this->validator->validate(['ab', 'hello', 42, 'world', 'x'], $schema);
        } catch (Throwable $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertTrue(true);
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
    public function max_contains_error_includes_min_detected_count(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer'),
            maxContains: 2,
        );

        try {
            $this->validator->validate([1, 2, 3, 4, 5], $schema);
            self::fail('Expected MaxContainsError was not thrown');
        } catch (MaxContainsError $e) {
            self::assertSame(2, $e->params()['maxContains']);
            self::assertSame(3, $e->params()['minDetectedCount']);
            self::assertStringContainsString('at least 3', $e->getMessage());
        }
    }

    #[Test]
    public function max_contains_error_message_does_not_claim_exact_count(): void
    {
        $schema = new Schema(
            type: 'array',
            contains: new Schema(type: 'integer'),
            maxContains: 1,
        );

        try {
            $this->validator->validate([1, 2, 3, 4], $schema);
            self::fail('Expected MaxContainsError was not thrown');
        } catch (MaxContainsError $e) {
            self::assertStringContainsString('at least', $e->getMessage());
            self::assertStringNotContainsString('has 2 matching items,', $e->getMessage());
        }
    }
}
