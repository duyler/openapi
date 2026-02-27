<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AdditionalPropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private AdditionalPropertiesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new AdditionalPropertiesValidator($this->pool);
    }

    #[Test]
    public function allow_additional_when_true(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            additionalProperties: true,
        );

        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function reject_additional_when_false(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            additionalProperties: false,
        );

        $this->expectException(ValidationException::class);

        $this->validator->validate(['name' => 'John', 'extra' => 'data'], $schema);
    }

    #[Test]
    public function validate_additional_against_schema(): void
    {
        $nameSchema = new Schema(type: 'string');
        $additionalSchema = new Schema(type: 'string', maxLength: 5);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            additionalProperties: $additionalSchema,
        );

        $this->validator->validate(['name' => 'John', 'key' => 'val'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function throw_error_for_invalid_additional_property(): void
    {
        $nameSchema = new Schema(type: 'string');
        $additionalSchema = new Schema(type: 'string', maxLength: 5);
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            additionalProperties: $additionalSchema,
        );

        $this->expectException(MaxLengthError::class);

        $this->validator->validate(['name' => 'John', 'key' => 'toolong'], $schema);
    }

    #[Test]
    public function allow_no_additional_properties(): void
    {
        $nameSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            additionalProperties: false,
        );

        $this->validator->validate(['name' => 'John'], $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_validation_for_non_object(): void
    {
        $schema = new Schema(
            type: 'object',
            additionalProperties: false,
        );

        $this->validator->validate('string value', $schema);

        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function skip_when_additional_properties_is_null(): void
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
    public function allow_multiple_additional_properties(): void
    {
        $nameSchema = new Schema(type: 'string');
        $additionalSchema = new Schema(type: 'string');
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => $nameSchema,
            ],
            additionalProperties: $additionalSchema,
        );

        $this->validator->validate([
            'name' => 'John',
            'key1' => 'val1',
            'key2' => 'val2',
            'key3' => 'val3',
        ], $schema);

        $this->expectNotToPerformAssertions();
    }
}
