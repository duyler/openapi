<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DuplicateItemsError;
use Duyler\OpenApi\Validator\Exception\MaxItemsError;
use Duyler\OpenApi\Validator\Exception\MinItemsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function count;
use function is_array;

use const SORT_REGULAR;

final readonly class ArrayLengthValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_array($data)) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
        $count = count($data);

        if (null !== $schema->minItems && $count < $schema->minItems) {
            throw new MinItemsError(
                minItems: $schema->minItems,
                actualCount: $count,
                dataPath: $dataPath,
                schemaPath: '/minItems',
            );
        }

        if (null !== $schema->maxItems && $count > $schema->maxItems) {
            throw new MaxItemsError(
                maxItems: $schema->maxItems,
                actualCount: $count,
                dataPath: $dataPath,
                schemaPath: '/maxItems',
            );
        }

        if (true === $schema->uniqueItems) {
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
