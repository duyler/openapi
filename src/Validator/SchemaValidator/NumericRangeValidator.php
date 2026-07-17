<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\InvalidMultipleOfSchemaException;
use Duyler\OpenApi\Validator\Exception\MaximumError;
use Duyler\OpenApi\Validator\Exception\MinimumError;
use Duyler\OpenApi\Validator\Exception\MultipleOfKeywordError;
use Override;

use function abs;
use function extension_loaded;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function max;
use function round;
use function sprintf;
use function assert;

final readonly class NumericRangeValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    private const float RELATIVE_EPSILON_FACTOR = 1e-9;

    private const float LARGE_QUOTIENT_THRESHOLD = 1e15;

    private const int BCMATH_SCALE = 20;

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->minimum
            || null !== $schema->maximum
            || null !== $schema->exclusiveMinimum
            || null !== $schema->exclusiveMaximum
            || null !== $schema->multipleOf;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_int($data) && false === is_float($data)) {
            return;
        }

        if (is_nan($data) || is_infinite($data)) {
            throw new InvalidDataTypeException(sprintf(
                'Numeric value must be finite JSON number, %s given',
                is_nan($data) ? 'NaN' : 'Infinity',
            ));
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
                throw InvalidMultipleOfSchemaException::forNonPositiveValue($schema->multipleOf);
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

        if (is_int($data) && extension_loaded('bcmath')) {
            $quotient = bcdiv((string) $data, (string) $multipleOf, self::BCMATH_SCALE);
            $floored = bcfloor($quotient);
            assert(is_numeric($floored));

            return 0 === bccomp($quotient, $floored, self::BCMATH_SCALE);
        }

        $quotient = (float) $data / $multipleOf;
        $rounded = round($quotient);
        $epsilon = self::RELATIVE_EPSILON_FACTOR * max(1.0, abs($quotient));

        if (is_int($data) && abs($quotient) > self::LARGE_QUOTIENT_THRESHOLD) {
            throw InvalidMultipleOfSchemaException::forLargeIntegerWithoutBcmath($data, $multipleOf);
        }

        return abs($quotient - $rounded) < $epsilon;
    }
}
