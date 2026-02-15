<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function array_slice;
use function count;
use function is_array;

use const PHP_INT_MAX;

readonly class UnevaluatedItemsValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->unevaluatedItems) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $evaluatedCount = $this->getEvaluatedItemsCount($schema);
        $unevaluatedItems = array_slice($data, $evaluatedCount);

        foreach ($unevaluatedItems as $item) {
            /** @var array-key|array<array-key, mixed> $item */
            $validator = new SchemaValidator($this->pool);
            $nullableAsType = $context?->nullableAsType ?? true;
            $itemContext = $context ?? ValidationContext::create($this->pool, $nullableAsType);
            $validator->validate($item, $schema->unevaluatedItems, $itemContext);
        }
    }

    private function getEvaluatedItemsCount(Schema $schema): int
    {
        if (null !== $schema->prefixItems && [] !== $schema->prefixItems) {
            return count($schema->prefixItems);
        }

        if (null !== $schema->items) {
            return PHP_INT_MAX;
        }

        return 0;
    }
}
