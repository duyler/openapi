<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use Duyler\OpenApi\Validator\SchemaValidator\Trait\LengthValidationTrait;
use Override;

use JsonException;

use function bin2hex;
use function count;
use function hash;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_nan;
use function is_resource;
use function is_string;
use function json_encode;
use function random_bytes;
use function serialize;

use const JSON_THROW_ON_ERROR;

final readonly class ArrayLengthValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    use LengthValidationTrait;

    /**
     * Above this size, mixed arrays switch from canonical-string deduplication
     * to xxh64-hash buckets to avoid storing large JSON blobs in `seen`. Hash
     * collisions are resolved by deep comparison so correctness is preserved.
     */
    private const int HASH_MODE_THRESHOLD = 100;

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->minItems
            || null !== $schema->maxItems
            || true === $schema->uniqueItems;
    }

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
     * @param array<mixed> $data
     */
    private function countUniqueItems(array $data): int
    {
        if ([] === $data) {
            return 0;
        }

        if (count($data) > self::HASH_MODE_THRESHOLD && $this->containsNonScalar($data)) {
            return $this->countUniqueByHash($data);
        }

        return $this->countUniqueByKey($data);
    }

    /**
     * @param array<mixed> $data
     */
    private function containsNonScalar(array $data): bool
    {
        return array_any($data, fn($item) => null !== $item && !is_int($item) && !is_float($item) && !is_string($item) && !is_bool($item));
    }

    /**
     * @param array<mixed> $data
     */
    private function countUniqueByKey(array $data): int
    {
        $seen = [];
        $count = 0;

        /** @var mixed $item */
        foreach ($data as $item) {
            $key = $this->itemKey($item);

            if (false === isset($seen[$key])) {
                $seen[$key] = true;
                ++$count;
            }
        }

        return $count;
    }

    /**
     * Hash-based deduplication for large mixed arrays. Items are bucketed by
     * xxh64 of their canonical key; bucket members are compared by deep
     * equality to preserve correctness on the (astronomically rare) hash
     * collision.
     *
     * @param array<mixed> $data
     */
    private function countUniqueByHash(array $data): int
    {
        /** @var array<string, list<mixed>> $buckets */
        $buckets = [];
        $count = 0;

        /** @var mixed $item */
        foreach ($data as $item) {
            $this->ensureJsonCompatible($item);

            $canonical = $this->itemKey($item);
            $bucketKey = hash('xxh64', $canonical);

            if (false === isset($buckets[$bucketKey])) {
                $buckets[$bucketKey] = [$item];
                ++$count;
                continue;
            }

            if (false === $this->bucketContainsDuplicate($buckets[$bucketKey], $item)) {
                $buckets[$bucketKey] = [...$buckets[$bucketKey], $item];
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<mixed> $bucket
     */
    private function bucketContainsDuplicate(array $bucket, mixed $item): bool
    {
        return array_any($bucket, fn($existing) => $this->itemsEqual($item, $existing));
    }

    private function itemsEqual(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            return $this->encodeArrayKey($a) === $this->encodeArrayKey($b);
        }

        if (is_array($a) || is_array($b)) {
            return false;
        }

        return JsonEquals::equals($a, $b);
    }

    private function itemKey(mixed $item): string
    {
        $this->ensureJsonCompatible($item);

        if (is_float($item) && is_nan($item)) {
            return 'n:nan:' . bin2hex(random_bytes(8));
        }

        if (is_int($item) || is_float($item)) {
            return 'n:' . (string) (float) $item;
        }

        if (null === $item) {
            return 'null';
        }

        if (is_bool($item)) {
            return 'b:' . ($item ? '1' : '0');
        }

        if (is_string($item)) {
            return 's:' . $item;
        }

        if (is_array($item)) {
            return 'a:' . $this->encodeArrayKey($item);
        }

        return serialize($item);
    }

    private function ensureJsonCompatible(mixed $value): void
    {
        if (is_resource($value)) {
            throw new InvalidDataTypeException('Resources are not valid JSON values');
        }
    }

    private function encodeArrayKey(array $item): string
    {
        try {
            return json_encode($item, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return serialize($item);
        }
    }
}
