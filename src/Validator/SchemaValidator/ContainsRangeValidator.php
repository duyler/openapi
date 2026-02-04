<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaxContainsError;
use Duyler\OpenApi\Validator\Exception\MinContainsError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Exception;
use Override;

use function is_array;

final readonly class ContainsRangeValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->contains && null === $schema->minContains && null === $schema->maxContains) {
            return;
        }

        if (null === $schema->contains) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;
        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
        $matchCount = 0;

        foreach ($data as $item) {
            try {
                /** @var array-key|array<array-key, mixed> $item */
                $validator = new SchemaValidator($this->pool);
                $itemContext = $context ?? ValidationContext::create($this->pool, $nullableAsType);
                $validator->validate($item, $schema->contains, $itemContext);
                ++$matchCount;
            } catch (Exception) {
            }
        }

        if (null !== $schema->minContains && $matchCount < $schema->minContains) {
            throw new MinContainsError(
                minContains: $schema->minContains,
                actualCount: $matchCount,
                dataPath: $dataPath,
                schemaPath: '/minContains',
            );
        }

        if (null !== $schema->maxContains && $matchCount > $schema->maxContains) {
            throw new MaxContainsError(
                maxContains: $schema->maxContains,
                actualCount: $matchCount,
                dataPath: $dataPath,
                schemaPath: '/maxContains',
            );
        }
    }
}
