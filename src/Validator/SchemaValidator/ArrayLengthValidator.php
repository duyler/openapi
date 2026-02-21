<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\SchemaValidator\Trait\LengthValidationTrait;
use Override;

use function count;
use function is_array;

use const SORT_REGULAR;

readonly class ArrayLengthValidator extends AbstractSchemaValidator
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
            $unique = array_unique($data, SORT_REGULAR);

            if (count($unique) !== $count) {
                throw new DuplicateItemsError(
                    expectedCount: $count,
                    actualCount: count($unique),
                    dataPath: $dataPath,
                    schemaPath: '/uniqueItems',
                );
            }
        }
    }
}
