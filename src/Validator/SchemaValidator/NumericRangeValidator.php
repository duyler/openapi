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
use function assert;
use function explode;
use function extension_loaded;
use function is_float;
use function is_infinite;
use function is_int;
use function is_nan;
use function ltrim;
use function max;
use function round;
use function rtrim;
use function sprintf;
use function str_contains;
use function str_repeat;
use function str_starts_with;
use function strlen;
use function substr;

final readonly class NumericRangeValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    private const float RELATIVE_EPSILON_FACTOR = 1e-9;

    private const int BCMATH_SCALE = 20;

    private const int MULTIPLEOF_STRING_PRECISION = 14;

    private const int MAX_DECIMAL_SUBTRACTIONS_PER_DIGIT = 10;

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

        if (is_int($data)) {
            return $this->isBigIntMultipleOfString($data, $multipleOf);
        }

        $quotient = $data / $multipleOf;
        $rounded = round($quotient);
        $epsilon = self::RELATIVE_EPSILON_FACTOR * max(1.0, abs($quotient));

        return abs($quotient - $rounded) < $epsilon;
    }

    private function isBigIntMultipleOfString(int $data, float $multipleOf): bool
    {
        $dataStr = (string) $data;
        if (str_starts_with($dataStr, '-')) {
            $dataStr = substr($dataStr, 1);
        }

        $absMultipleOf = abs($multipleOf);
        $divisorStr = sprintf('%.' . self::MULTIPLEOF_STRING_PRECISION . 'F', $absMultipleOf);
        $divisorStr = rtrim(rtrim($divisorStr, '0'), '.');

        if (str_contains($divisorStr, '.')) {
            $parts = explode('.', $divisorStr, 2);
            $decimalPlaces = strlen($parts[1] ?? '');
            $scaledDivisor = ltrim(($parts[0] ?? '') . ($parts[1] ?? ''), '0') ?: '0';
        } else {
            $decimalPlaces = 0;
            $scaledDivisor = $divisorStr;
        }

        $scaledData = $dataStr . str_repeat('0', $decimalPlaces);

        return '0' === $this->decimalMod($scaledData, $scaledDivisor);
    }

    private function decimalMod(string $dividend, string $divisor): string
    {
        $dividend = ltrim($dividend, '0') ?: '0';
        $divisor = ltrim($divisor, '0') ?: '0';

        if ('0' === $divisor || '0' === $dividend) {
            return $dividend;
        }

        $remainder = '';
        $len = strlen($dividend);

        for ($i = 0; $i < $len; ++$i) {
            $remainder .= $dividend[$i];
            $remainder = ltrim($remainder, '0') ?: '0';

            for ($k = 0; $k < self::MAX_DECIMAL_SUBTRACTIONS_PER_DIGIT; ++$k) {
                if ($this->decimalCmp($remainder, $divisor) < 0) {
                    break;
                }
                $remainder = $this->decimalSub($remainder, $divisor);
            }
        }

        return $remainder;
    }

    private function decimalCmp(string $a, string $b): int
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        $lenDiff = strlen($a) <=> strlen($b);
        if (0 !== $lenDiff) {
            return $lenDiff;
        }

        return $a <=> $b;
    }

    private function decimalSub(string $a, string $b): string
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';

        $result = '';
        $borrow = 0;
        $j = strlen($b) - 1;

        for ($i = strlen($a) - 1; $i >= 0; --$i, --$j) {
            $digitA = (int) $a[$i];
            $digitB = $j >= 0 ? (int) $b[$j] : 0;
            $diff = $digitA - $digitB - $borrow;

            if ($diff < 0) {
                $diff += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = $diff . $result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
