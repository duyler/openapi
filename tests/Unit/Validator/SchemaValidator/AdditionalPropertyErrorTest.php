<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\AdditionalPropertyError;
use Duyler\OpenApi\Validator\Exception\UnevaluatedPropertyError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for FU-006: `additionalProperties: false` violations must surface
 * `AdditionalPropertyError` (keyword: 'additionalProperties') instead of the semantically
 * wrong `UnevaluatedPropertyError` (keyword: 'unevaluatedProperties').
 */
#[CoversClass(AdditionalPropertiesValidator::class)]
final class AdditionalPropertyErrorTest extends TestCase
{
    private AdditionalPropertiesValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new AdditionalPropertiesValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function additional_properties_false_returns_additional_properties_keyword(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['bar' => new Schema(type: 'string')],
            additionalProperties: false,
        );

        $caught = null;

        try {
            $this->validator->validate(['bar' => 'ok', 'foo' => 1], $schema);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertCount(1, $caught->getErrors());
        self::assertSame('additionalProperties', $caught->getErrors()[0]->keyword());
    }

    #[Test]
    public function additional_properties_false_returns_additional_property_error_class(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['bar' => new Schema(type: 'string')],
            additionalProperties: false,
        );

        $caught = null;

        try {
            $this->validator->validate(['bar' => 'ok', 'foo' => 1], $schema);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame(AdditionalPropertyError::class, $caught->getErrors()[0]::class);
    }

    #[Test]
    public function additional_properties_false_returns_correct_schema_path(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['bar' => new Schema(type: 'string')],
            additionalProperties: false,
        );

        $caught = null;

        try {
            $this->validator->validate(['bar' => 'ok', 'foo' => 1], $schema);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('/additionalProperties', $caught->getErrors()[0]->schemaPath());
    }

    #[Test]
    public function additional_properties_false_returns_property_name_in_params(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: ['bar' => new Schema(type: 'string')],
            additionalProperties: false,
        );

        $caught = null;

        try {
            $this->validator->validate(['bar' => 'ok', 'foo' => 1], $schema);
        } catch (ValidationException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('foo', $caught->getErrors()[0]->params()['propertyName']);
    }

    /**
     * Anti-test: proves the semantic mismatch that existed before FU-006.
     * `AdditionalPropertyError` must report keyword 'additionalProperties',
     * while `UnevaluatedPropertyError` reports 'unevaluatedProperties' for the
     * distinct `unevaluatedProperties` JSON Schema keyword.
     */
    #[Test]
    public function additional_property_error_differs_from_unevaluated_property_error(): void
    {
        $additionalError = new AdditionalPropertyError('/', '/additionalProperties', 'foo');
        $unevaluatedError = new UnevaluatedPropertyError('/', '/unevaluatedProperties', 'foo');

        self::assertSame('additionalProperties', $additionalError->keyword());
        self::assertSame('unevaluatedProperties', $unevaluatedError->keyword());
        self::assertNotSame($additionalError::class, $unevaluatedError::class);
    }
}
