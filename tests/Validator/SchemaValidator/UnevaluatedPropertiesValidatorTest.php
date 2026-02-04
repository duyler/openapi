<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UnevaluatedPropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private UnevaluatedPropertiesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new UnevaluatedPropertiesValidator($this->pool);
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

        $this->validator->validate(['name' => 'John', 'extra' => 'any data'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function validate_empty_object(): void
    {
        $schema = new Schema(
            type: 'object',
            unevaluatedProperties: false,
        );

        $this->validator->validate([], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 'num_1' => 42], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 'prop_test' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 'prop_1' => 'val1', 'prop_2' => 'val2'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['str_test' => 'hello', 'num_42' => 123], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 'extra' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 'extra' => 'value'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['test_a' => 'val1', 'test_b' => 'val2'], $schema);

        $this->expectNotToPerformAssertions();
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

        $this->validator->validate(['name' => 'John', 0 => 'numeric_key', 1 => 'another_numeric'], $schema);

        $this->expectNotToPerformAssertions();
    }
}
