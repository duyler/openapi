<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * FU-017: regression tests for the residual DoS vector in the
 * ValidationException message built by AdditionalPropertiesValidator.
 *
 * Before the fix the message was assembled with
 * `implode(', ', $additionalKeys)` over the FULL set of additional keys,
 * which allocated ~5MB for 100k properties even though the `$errors`
 * array was already capped at MAX_ADDITIONAL_PROPERTY_ERRORS (FU-008).
 *
 * After the fix the message is assembled exclusively from the already
 * capped `$errors` array (<= 101 elements including the truncation
 * indicator), bounding the message allocation regardless of payload size.
 */
#[CoversClass(AdditionalPropertiesValidator::class)]
class AdditionalPropertiesValidatorImplodeCapTest extends TestCase
{
    private const int CAP_THRESHOLD = 100;

    private const int MESSAGE_SIZE_LIMIT_BYTES = 50_000;

    private AdditionalPropertiesValidator $validator;

    #[Override]
    protected function setUp(): void
    {
        $this->validator = new AdditionalPropertiesValidator(new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()));
    }

    #[Test]
    public function large_payload_message_is_capped(): void
    {
        $data = $this->buildDataWithAdditionalProperties(1000);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);
        self::assertLessThan(
            self::MESSAGE_SIZE_LIMIT_BYTES,
            strlen($caught->getMessage()),
            'ValidationException message must stay bounded for large payloads.',
        );
    }

    #[Test]
    public function large_payload_message_size_under_50kb(): void
    {
        $data = $this->buildDataWithAdditionalProperties(1000);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);

        // Sanity bound: 100 capped names plus the indicator stay well under 50KB.
        // A naive `implode(', ', $additionalKeys)` over 1000 keys produces ~10KB
        // here and over 5MB for 100k keys — this assert catches a regression.
        self::assertLessThan(self::MESSAGE_SIZE_LIMIT_BYTES, strlen($caught->getMessage()));
    }

    #[Test]
    public function huge_payload_message_size_under_50kb(): void
    {
        // 100k additional properties would have allocated ~5MB in the message
        // before FU-017. After the fix the message is built from the capped
        // `$errors` array, so it stays bounded regardless of payload size.
        $data = $this->buildDataWithAdditionalProperties(100_000);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);
        self::assertLessThan(self::MESSAGE_SIZE_LIMIT_BYTES, strlen($caught->getMessage()));
    }

    #[Test]
    public function large_payload_errors_capped_at_100_plus_indicator(): void
    {
        $data = $this->buildDataWithAdditionalProperties(1000);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);
        self::assertCount(self::CAP_THRESHOLD + 1, $caught->getErrors());
    }

    #[Test]
    public function large_payload_message_contains_truncation_indicator(): void
    {
        $data = $this->buildDataWithAdditionalProperties(1000);
        $schema = $this->buildSchemaDisallowingAdditionalProperties();

        $caught = $this->catchValidationException($data, $schema);

        self::assertNotNull($caught);
        self::assertStringContainsString(
            '... and 900 more additional properties',
            $caught->getMessage(),
            'Truncation indicator must be present in the capped message.',
        );
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
