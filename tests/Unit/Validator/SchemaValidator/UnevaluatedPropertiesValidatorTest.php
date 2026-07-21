<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
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
        $this->validator = new UnevaluatedPropertiesValidator(new ValidatorDependencies(pool: $this->pool, formatRegistry: BuiltinFormats::create()));
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
    public function passes_when_additional_properties_schema_with_unevaluated_false(): void
    {
        $idSchema = new Schema(type: 'integer');
        $additionalSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => $idSchema,
            ],
            additionalProperties: $additionalSchema,
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['id' => 1, 'extra' => 'hello'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function passes_when_additional_properties_true_with_unevaluated_false(): void
    {
        $idSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => $idSchema,
            ],
            additionalProperties: true,
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['id' => 1, 'extra' => 'hello'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function throws_when_additional_properties_false_with_unevaluated_false(): void
    {
        $idSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => $idSchema,
            ],
            additionalProperties: false,
            unevaluatedProperties: false,
        );

        $this->expectException(UnevaluatedPropertyError::class);

        $this->validator->validate(['id' => 1, 'extra' => 'hello'], $schema);
    }

    #[Test]
    public function passes_when_no_additional_properties_and_property_defined(): void
    {
        $idSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => $idSchema,
            ],
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['id' => 1], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * C-001 regression: properties validated by an in-place applicator
     * (here simulated by directly marking them in context, as
     * AllOfValidator would after a successful branch) must be treated
     * as evaluated by unevaluatedProperties.
     */
    #[Test]
    public function unevaluated_properties_with_context_annotations_passes(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
        );

        $context = ValidationContext::create($this->pool);
        $context->markPropertyEvaluated('name');

        $succeeded = false;

        try {
            $this->validator->validate(['name' => 'Alice'], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass when "name" is evaluated via annotation, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_partial_context_annotations_fails_on_unevaluated(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
        );

        $context = ValidationContext::create($this->pool);
        $context->markPropertyEvaluated('known');

        $this->expectException(UnevaluatedPropertyError::class);

        $this->validator->validate(['known' => 1, 'unknown' => 2], $schema, $context);
    }

    /**
     * C-004 regression: annotations from composition validators
     * (allOf/anyOf/oneOf/if-then-else) merge with static analysis
     * of properties / patternProperties.
     */
    #[Test]
    public function unevaluated_properties_merges_static_and_annotation_evaluated(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'static' => $nameSchema,
            ],
            unevaluatedProperties: false,
        );

        $context = ValidationContext::create($this->pool);
        $context->markPropertyEvaluated('from_annotation');

        $succeeded = false;

        try {
            $this->validator->validate(['static' => 'value', 'from_annotation' => 42], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass when both static and annotation-evaluated properties cover the data, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * C-001 regression: when a property is evaluated by an annotation
     * AND present in patternProperties, both contribute to evaluated
     * set; no double-counting issues.
     */
    #[Test]
    public function unevaluated_properties_with_pattern_properties_and_annotations(): void
    {
        $patternSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            patternProperties: [
                '/^x_/' => $patternSchema,
            ],
            unevaluatedProperties: false,
        );

        $context = ValidationContext::create($this->pool);
        $context->markPropertyEvaluated('annotated');

        $succeeded = false;

        try {
            $this->validator->validate(['x_foo' => 'value', 'annotated' => 1], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass for pattern-matched x_foo and annotation-evaluated "annotated", got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_additional_properties_true_registers_all_keys(): void
    {
        $additional = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            additionalProperties: $additional,
            unevaluatedProperties: false,
        );

        $succeeded = false;

        try {
            $this->validator->validate(['any_key' => 'value', 'other' => 'val'], $schema);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass for additionalProperties:schema covering all keys, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    #[Test]
    public function unevaluated_properties_with_schema_uses_annotation_set(): void
    {
        $unevaluatedSchema = new Schema(type: 'integer');
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: $unevaluatedSchema,
        );

        $context = ValidationContext::create($this->pool);
        $context->markPropertyEvaluated('evaluated_by_branch');

        $succeeded = false;

        try {
            $this->validator->validate(['evaluated_by_branch' => 'anything', 'extra' => 42], $schema, $context);
            $succeeded = true;
        } catch (RuntimeException $e) {
            self::fail(sprintf('Expected validation to pass when "evaluated_by_branch" is in annotations and "extra" matches unevaluatedItems schema, got: %s', $e->getMessage()));
        }

        self::assertSame(true, $succeeded);
    }

    /**
     * C-001 regression: without context (legacy SchemaValidator path),
     * the validator falls back to static analysis only. Properties not
     * declared in static schema are flagged as unevaluated.
     */
    #[Test]
    public function unevaluated_properties_without_context_uses_static_analysis_only(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'declared' => $nameSchema,
            ],
            unevaluatedProperties: false,
        );

        $this->expectException(UnevaluatedPropertyError::class);

        $this->validator->validate(['declared' => 'value', 'undeclared' => 1], $schema, null);
    }

    #[Test]
    public function unevaluated_properties_fails_on_truly_unevaluated(): void
    {
        $known = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'known' => $known,
            ],
            unevaluatedProperties: false,
        );

        $context = ValidationContext::create($this->pool);

        $this->expectException(UnevaluatedPropertyError::class);

        $this->validator->validate(['known' => 'value', 'unknown' => 1], $schema, $context);
    }
}
