<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Override;

use function abs;
use function is_float;
use function is_int;
use function max;
use function round;

final readonly class NumericRangeValidator extends AbstractSchemaValidator
{
    private const float RELATIVE_EPSILON_FACTOR = 1e-9;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_int($data) && false === is_float($data)) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        if (null !== $schema->minimum && $data < $schema->minimum) {
            throw new MinimumError(
                minimum: $schema->minimum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/minimum',
            );
        }

        if (null !== $schema->exclusiveMinimum && $data <= $schema->exclusiveMinimum) {
            throw new MinimumError(
                minimum: $schema->exclusiveMinimum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/exclusiveMinimum',
            );
        }

        if (null !== $schema->maximum && $data > $schema->maximum) {
            throw new MaximumError(
                maximum: $schema->maximum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/maximum',
            );
        }

        if (null !== $schema->exclusiveMaximum && $data >= $schema->exclusiveMaximum) {
            throw new MaximumError(
                maximum: $schema->exclusiveMaximum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/exclusiveMaximum',
            );
        }

        if (null !== $schema->multipleOf) {
            if (0.0 === $schema->multipleOf) {
                throw new MultipleOfKeywordError(
                    multipleOf: $schema->multipleOf,
                    value: $data,
                    dataPath: $dataPath,
                    schemaPath: '/multipleOf',
                );
            }

            if (false === $this->isMultipleOf($data, $schema->multipleOf)) {
                throw new MultipleOfKeywordError(
                    multipleOf: $schema->multipleOf,
                    value: $data,
                    dataPath: $dataPath,
                    schemaPath: '/multipleOf',
                );
            }
        }
    }

    private function isMultipleOf(int|float $data, float $multipleOf): bool
    {
        if (is_int($data) && (float) (int) $multipleOf === $multipleOf) {
            return 0 === ($data % (int) $multipleOf);
        }

        $quotient = (float) $data / $multipleOf;
        $rounded = round($quotient);
        $epsilon = self::RELATIVE_EPSILON_FACTOR * max(1.0, abs($quotient));

        return abs($quotient - $rounded) < $epsilon;
    }
}
