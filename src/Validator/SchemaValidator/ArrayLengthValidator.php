<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\Exception\TooManyItemsForUniqueCheckError;
use Duyler\OpenApi\Validator\Schema\JsonEquals;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\ScalarKeyCodec;
use Duyler\OpenApi\Validator\SchemaValidator\Trait\LengthValidationTrait;
use Override;

use JsonException;

use function array_is_list;
use function array_map;
use function count;
use function hash;
use function is_array;
use function is_object;
use function is_resource;
use function is_scalar;
use function json_encode;
use function ksort;
use function serialize;

use const JSON_THROW_ON_ERROR;
use const SORT_STRING;

final readonly class ArrayLengthValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    use LengthValidationTrait;

    private const int HASH_MODE_THRESHOLD = 100;

    private const int MAX_UNIQUE_CHECK = 100000;

    private readonly ScalarKeyCodec $scalarKeyCodec;

    public function __construct(SchemaValidatorDependencies $dependencies)
    {
        parent::__construct($dependencies);
        $this->scalarKeyCodec = new ScalarKeyCodec();
    }

    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->minItems
            || null !== $schema->maxItems
            || $schema->uniqueItems;
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
            $uniqueCount = $this->countUniqueItems($data, $dataPath);

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
    private function countUniqueItems(array $data, string $dataPath): int
    {
        if (count($data) > self::HASH_MODE_THRESHOLD && $this->containsNonScalar($data)) {
            return $this->countUniqueByHash($data, $dataPath);
        }

        return $this->countUniqueByKey($data, $dataPath);
    }

    /**
     * @param array<mixed> $data
     */
    private function containsNonScalar(array $data): bool
    {
        return array_any($data, fn($item) => null !== $item && false === is_scalar($item));
    }

    /**
     * @param array<mixed> $data
     */
    private function countUniqueByKey(array $data, string $dataPath): int
    {
        $seen = [];
        $count = 0;

        /** @var mixed $item */
        foreach ($data as $item) {
            $key = $this->itemKey($item);

            if (false === isset($seen[$key])) {
                $seen[$key] = true;
                ++$count;
                $this->enforceUniqueCheckLimit($count, $dataPath);
            }
        }

        return $count;
    }

    /**
     * @param array<mixed> $data
     */
    private function countUniqueByHash(array $data, string $dataPath): int
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
                $this->enforceUniqueCheckLimit($count, $dataPath);
                continue;
            }

            if (false === array_any($buckets[$bucketKey], fn($existing) => $this->itemsEqual($item, $existing))) {
                $buckets[$bucketKey] = [...$buckets[$bucketKey], $item];
                ++$count;
                $this->enforceUniqueCheckLimit($count, $dataPath);
            }
        }

        return $count;
    }

    private function enforceUniqueCheckLimit(int $count, string $dataPath): void
    {
        if (self::MAX_UNIQUE_CHECK < $count) {
            throw new TooManyItemsForUniqueCheckError(max: self::MAX_UNIQUE_CHECK, dataPath: $dataPath);
        }
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

        if (is_array($item)) {
            return 'a:' . $this->encodeArrayKey($item);
        }

        if (is_object($item)) {
            return serialize($item);
        }

        return $this->scalarKeyCodec->encode($item);
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
            return json_encode(
                $this->canonicalizeForEncoding($item),
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            return serialize($item);
        }
    }

    /**
     * @param array<array-key, mixed> $item
     * @return array<array-key, mixed>
     */
    private function canonicalizeForEncoding(array $item): array
    {
        $canonical = array_map(
            fn(mixed $value): mixed => is_array($value) ? $this->canonicalizeForEncoding($value) : $value,
            $item,
        );

        if (false === array_is_list($canonical)) {
            ksort($canonical, SORT_STRING);
        }

        return $canonical;
    }
}
