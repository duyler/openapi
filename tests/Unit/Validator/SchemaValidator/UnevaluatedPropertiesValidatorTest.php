<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function sprintf;

#[CoversClass(UnevaluatedPropertiesValidator::class)]
class UnevaluatedPropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private UnevaluatedPropertiesValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new UnevaluatedPropertiesValidator($this->pool, BuiltinFormats::create());
    }

    #[Test]
    public function allow_all_when_unevaluated_properties_is_true(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'extra' => 'any data'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throw_error_when_unevaluated_properties_is_false(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: false,
        );

        $this->expectException(UnevaluatedPropertyError::class);

        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
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
    public function skip_when_unevaluated_properties_is_null(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_empty_object(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate([], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_with_properties(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function track_properties(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_no_additional(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_with_pattern_properties(): void
    {
        $nameSchema = new Schema(type: 'string');
        $patternSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            patternProperties: [
                '/^num_/' => $patternSchema,
            ],
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'num_1' => 42], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_all_evaluated(): void
    {
        $nameSchema = new Schema(type: 'string');
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            patternProperties: [
                '/^prop_/' => $patternSchema,
            ],
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'prop_test' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function track_pattern_properties(): void
    {
        $nameSchema = new Schema(type: 'string');
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            patternProperties: [
                '/^prop_/' => $patternSchema,
            ],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'prop_1' => 'val1', 'prop_2' => 'val2'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_with_pattern_matching(): void
    {
        $patternSchema1 = new Schema(type: 'string');
        $patternSchema2 = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^str_/' => $patternSchema1,
                '/^num_/' => $patternSchema2,
            ],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['str_test' => 'hello', 'num_42' => 123], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_pattern_with_empty_string(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            patternProperties: [
                '' => new Schema(type: 'string'),
            ],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'extra' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_pattern_properties_with_empty_array(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            patternProperties: [],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'extra' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function track_only_pattern_properties(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^test_/' => $patternSchema,
            ],
            unevaluatedProperties: true,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['test_a' => 'val1', 'test_b' => 'val2'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function skip_numeric_property_names(): void
    {
        $nameSchema = new Schema(type: 'string');
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            patternProperties: [
                '/^prop_/' => $patternSchema,
            ],
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 0 => 'numeric_key', 1 => 'another_numeric'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_with_schema_object(): void
    {
        $nameSchema = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: $unevaluatedSchema,
        );

        // 'extra' is unevaluated and should be validated against unevaluatedSchema
        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John', 'extra' => 'value'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_schema_object_with_invalid_value_throws(): void
    {
        $nameSchema = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: $unevaluatedSchema,
        );

        $this->expectException(TypeMismatchError::class);

        // 'extra' is unevaluated and 'string' does not match integer schema
        $this->validator->validate(['name' => 'John', 'extra' => 'not_an_integer'], $schema);
    }

    #[Test]
    public function validate_unevaluated_properties_schema_object_without_context(): void
    {
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: $unevaluatedSchema,
        );

        // No ValidationContext provided — should use default
        $succeeded = false;

        try {
            $this->validator->validate(['extra' => 'value'], $schema, null);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function validate_unevaluated_properties_schema_object_all_evaluated(): void
    {
        $nameSchema = new Schema(type: 'string');
        $unevaluatedSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: $unevaluatedSchema,
        );

        // No unevaluated properties — schema validation not triggered
        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'John'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }
}
