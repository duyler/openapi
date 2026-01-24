<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
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
    public function skip_when_unevaluated_properties_is_false(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            unevaluatedProperties: false,
        );

        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);

        $this->expectNotToPerformAssertions();
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
}
