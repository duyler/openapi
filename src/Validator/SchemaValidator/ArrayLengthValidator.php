<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\SchemaValidator\Trait\LengthValidationTrait;
use Override;

use function array_key_exists;
use function count;
use function is_array;
use function is_float;
use function is_int;
use function is_resource;

final readonly class ArrayLengthValidator extends AbstractSchemaValidator
{
    use LengthValidationTrait;

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_array($data)) {
            return;
        }

        $dataPath = $this->getDataPath($context);
        $count = count($data);

        $this->validateLength(
            actual: $count,
            min: $schema->minItems,
            max: $schema->maxItems,
            minErrorFactory: static fn(int $min, int $actual) => new MinItemsError($min, $actual, $dataPath, '/minItems'),
            maxErrorFactory: static fn(int $max, int $actual) => new MaxItemsError($max, $actual, $dataPath, '/maxItems'),
        );

        if ($schema->uniqueItems) {
            $uniqueCount = $this->countUniqueItems($data);

            if ($uniqueCount !== $count) {
                throw new DuplicateItemsError(
                    expectedCount: $count,
                    actualCount: $uniqueCount,
                    dataPath: $dataPath,
                    schemaPath: '/uniqueItems',
                );
            }
        }
    }

    /**
     * Counts items in the array using JSON Schema draft 2020-12 §6.4.3 equality
     * semantics: scalars of different JSON types (number, string, boolean) are
     * never equal, while arrays/objects are compared by deep structural equality.
     *
     * The inspected values come from arbitrary JSON-decoded payloads, so every
     * element is intentionally treated as `mixed` and dispatched to isSameItem,
     * which re-establishes type narrowing per branch.
     *
     * @param array<mixed> $data
     * @psalm-suppress MixedAssignment
     */
    private function countUniqueItems(array $data): int
    {
        /** @var list<mixed> $unique */
        $unique = [];

        foreach ($data as $item) {
            $isDuplicate = false;

            foreach ($unique as $kept) {
                if ($this->isSameItem($item, $kept)) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $unique[] = $item;
            }
        }

        return count($unique);
    }

    /**
     * JSON Schema draft 2020-12 §6.4.3 deep equality:
     * - Scalars of different JSON types (number, string, boolean) are never
     *   equal: `1 !== "1" !== true`.
     * - Numbers (int and float) are equal when mathematically equal per JSON
     *   Schema §4.2.3: `1 === 1.0` (both represent the JSON number one).
     * - Arrays/objects use recursive structural comparison with the same
     *   scalar rules applied to every leaf value.
     */
    private function isSameItem(mixed $a, mixed $b): bool
    {
        $this->ensureJsonCompatible($a);
        $this->ensureJsonCompatible($b);

        if (is_array($a) && is_array($b)) {
            return $this->arrayEqual($a, $b);
        }

        // JSON Schema §4.2.3: numbers are equal if mathematically equal.
        // PHP's === distinguishes int from float (1 !== 1.0), but JSON Schema
        // treats them as the same value. Compare numerically when both are
        // numbers (int or float), preserving the distinction from "1" and true.
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return (float) $a === (float) $b;
        }

        // Strict comparison distinguishes remaining JSON types:
        // 1 !== "1" !== true. Also returns false when one side is an
        // array and the other is a scalar (mixed shapes are never equal).
        return $a === $b;
    }

    private function ensureJsonCompatible(mixed $value): void
    {
        if (is_resource($value)) {
            throw new InvalidDataTypeException('Resources are not valid JSON values');
        }
    }

    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     * @psalm-suppress MixedAssignment
     */
    private function arrayEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }

        foreach ($a as $key => $value) {
            if (!array_key_exists($key, $b)) {
                return false;
            }

            if (!$this->isSameItem($value, $b[$key])) {
                return false;
            }
        }

        return true;
    }
}
