<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\AdditionalPropertyError;
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
 * FU-008: regression tests for the DoS cap on AdditionalPropertyError objects
 * emitted by AdditionalPropertiesValidator when additionalProperties is false.
 */
#[CoversClass(AdditionalPropertiesValidator::class)]
class AdditionalPropertiesErrorCapTest extends TestCase
{
    private const int CAP_THRESHOLD = 100;

    private AdditionalPropertiesValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new AdditionalPropertiesValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function caps_additional_property_errors_at_100(): void
    {
        $data = $this->buildDataWithAdditionalProperties(200);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);

        $errors = $caught->getErrors();

        self::assertCount(self::CAP_THRESHOLD + 1, $errors);

        for ($i = 0; $i < self::CAP_THRESHOLD; ++$i) {
            self::assertInstanceOf(AdditionalPropertyError::class, $errors[$i]);
            self::assertSame('extra_' . $i, $errors[$i]->params()['propertyName']);
        }

        self::assertInstanceOf(AdditionalPropertyError::class, $errors[self::CAP_THRESHOLD]);
        self::assertSame(
            '... and 100 more additional properties',
            $errors[self::CAP_THRESHOLD]->params()['propertyName'],
        );
    }

    #[Test]
    public function does_not_cap_below_threshold(): void
    {
        $data = $this->buildDataWithAdditionalProperties(50);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);

        $errors = $caught->getErrors();

        self::assertCount(50, $errors);

        foreach ($errors as $error) {
            self::assertInstanceOf(AdditionalPropertyError::class, $error);
            self::assertStringStartsWith('extra_', $error->params()['propertyName']);
            self::assertStringNotContainsString('more additional properties', $error->params()['propertyName']);
        }
    }

    #[Test]
    public function boundary_at_exact_cap(): void
    {
        $data = $this->buildDataWithAdditionalProperties(self::CAP_THRESHOLD);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);

        $errors = $caught->getErrors();

        self::assertCount(self::CAP_THRESHOLD, $errors);

        foreach ($errors as $error) {
            self::assertInstanceOf(AdditionalPropertyError::class, $error);
            self::assertStringNotContainsString('more additional properties', $error->params()['propertyName']);
        }
    }

    #[Test]
    public function summary_error_uses_additional_property_error_with_truncation_marker(): void
    {
        $data = $this->buildDataWithAdditionalProperties(150);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);

        $errors = $caught->getErrors();
        $summary = $errors[self::CAP_THRESHOLD];

        self::assertInstanceOf(AdditionalPropertyError::class, $summary);
        self::assertSame('additionalProperties', $summary->keyword());
        self::assertSame('/additionalProperties', $summary->schemaPath());
        self::assertSame('... and 50 more additional properties', $summary->params()['propertyName']);
    }

    private function buildDataWithAdditionalProperties(int $count): array
    {
        $data = ['id' => 1];

        for ($i = 0; $i < $count; ++$i) {
            $data['extra_' . $i] = 'value';
        }

        return $data;
    }

    private function buildSchemaDisallowingAdditionalProperties(): Schema
    {
        return new Schema(
            type: 'object',
            properties: [
                'id' => new Schema(type: 'integer'),
            ],
            additionalProperties: false,
        );
    }

    private function catchValidationException(array $data, Schema $schema): ?ValidationException
    {
        try {
            $this->validator->validate($data, $schema);
        } catch (ValidationException $e) {
            return $e;
        }

        return null;
    }
}
