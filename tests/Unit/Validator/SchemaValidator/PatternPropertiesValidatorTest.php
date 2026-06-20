<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\InvalidPatternException;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(PatternPropertiesValidator::class)]
class PatternPropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private PatternPropertiesValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new PatternPropertiesValidator($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function validate_pattern_properties(): void
    {
        $patternSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^meta_/' => $patternSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['meta_info' => 'data'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function apply_multiple_patterns(): void
    {
        $patternSchema1 = new Schema(type: 'string', minLength: 3);
        $patternSchema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^str_/' => $patternSchema1,
                '/^num_/' => $patternSchema2,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['str_val' => 'hello', 'num_val' => 42], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_pattern_property(): void
    {
        $patternSchema = new Schema(type: 'string', minLength: 10);
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^meta_/' => $patternSchema,
            ],
        );

        $this->expectException(MinLengthError::class);

        $this->validator->validate(['meta_info' => 'short'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^key_/' => $patternSchema,
            ],
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
    public function skip_when_pattern_properties_is_null(): void
    {
        $schema = new Schema(type: 'object');

        $succeeded = false;

        try {
            $this->validator->validate(['key' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_when_pattern_properties_is_empty(): void
    {
        $schema = new Schema(type: 'object', patternProperties: []);

        $succeeded = false;

        try {
            $this->validator->validate(['key' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_non_matching_properties(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^meta_/' => $patternSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['other_key' => 'value', 'meta_info' => 'data'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_empty_pattern(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '' => $patternSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['key' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_numeric_keys(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^\d+$/' => $patternSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate([0 => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_pattern_properties_without_delimiters(): void
    {
        $patternSchema = new Schema(type: 'string', minLength: 3);
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^meta_' => $patternSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['meta_info' => 'data'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function apply_multiple_patterns_without_delimiters(): void
    {
        $patternSchema1 = new Schema(type: 'string', minLength: 3);
        $patternSchema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^str_' => $patternSchema1,
                '^num_' => $patternSchema2,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['str_val' => 'hello', 'num_val' => 42], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_for_invalid_regex_pattern(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '[invalid' => $patternSchema,
            ],
        );

        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "#[invalid#":');

        $this->validator->validate(['invalid' => 'a'], $schema);
    }

    #[Test]
    public function throw_error_for_invalid_regex_pattern_with_delimiters(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/[invalid/' => $patternSchema,
            ],
        );

        $this->expectException(InvalidPatternException::class);
        $this->expectExceptionMessage('Invalid regex pattern "/[invalid/":');

        $this->validator->validate(['invalid' => 'a'], $schema);
    }

    #[Test]
    public function mixed_patterns_with_and_without_delimiters(): void
    {
        $patternSchema1 = new Schema(type: 'string', minLength: 3);
        $patternSchema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '^str_' => $patternSchema1,
                '/^num_/' => $patternSchema2,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['str_val' => 'hello', 'num_val' => 42], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
