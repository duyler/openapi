<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\AdditionalPropertyError;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(AdditionalPropertiesValidator::class)]
class AdditionalPropertiesValidatorTest extends TestCase
{
    private ValidatorPool $pool;
    private AdditionalPropertiesValidator $validator;

    protected function setUp(): void
    {
        $this->pool = new ValidatorPool();
        $this->validator = new AdditionalPropertiesValidator($this->pool, BuiltinFormats::create());
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

    /**
     * EI-054 + FU-006: additionalProperties: false must produce one AdditionalPropertyError
     * per forbidden property instead of an empty errors array, and the keyword must be
     * 'additionalProperties' (not 'unevaluatedProperties').
     */
    #[Test]
    public function reject_additional_when_false_returns_additional_property_errors(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
            ],
            additionalProperties: false,
        );

        $caught = null;

        try {
            $this->validator->validate(['id' => 1, 'extra1' => 'a', 'extra2' => 'b'], $schema);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);

        $errors = $caught->getErrors();

        self::assertCount(2, $errors);
        self::assertInstanceOf(AdditionalPropertyError::class, $errors[0]);
        self::assertInstanceOf(AdditionalPropertyError::class, $errors[1]);
        self::assertSame('additionalProperties', $errors[0]->keyword());
        self::assertSame('additionalProperties', $errors[1]->keyword());
        self::assertSame('extra1', $errors[0]->params()['propertyName']);
        self::assertSame('extra2', $errors[1]->params()['propertyName']);
    }

    /**
     * Regression: no extra properties must still pass when additionalProperties is false.
     */
    #[Test]
    public function allow_no_additional_properties_when_additional_properties_false(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
            ],
            additionalProperties: false,
        );

        $this->validator->validate(['id' => 1], $schema);

        $this->expectNotToPerformAssertions();
    }
}
