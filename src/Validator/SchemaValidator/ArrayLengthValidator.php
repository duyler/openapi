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

use JsonException;

use function bin2hex;
use function count;
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
     * @param array<mixed> $data
     */
    private function countUniqueItems(array $data): int
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
