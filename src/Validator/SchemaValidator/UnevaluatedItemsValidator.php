<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function array_diff_key;
use function array_flip;
use function array_values;
use function count;
use function is_array;

final readonly class UnevaluatedItemsValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->unevaluatedItems;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->unevaluatedItems) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $evaluatedIndices = $this->getEvaluatedItemIndices($schema, $data, $context);
        /** @var list<int> $dataIndices */
        $dataIndices = array_keys($data);
        $unevaluatedIndices = array_values(array_diff_key(
            $dataIndices,
            array_flip($evaluatedIndices),
        ));

        $validator = $this->createSchemaValidator();
        $nullableAsType = $context?->nullableAsType ?? true;

        foreach ($unevaluatedIndices as $index) {
            /** @var array-key|array<array-key, mixed> $item */
            $item = $data[$index];

            if (null === $context) {
                $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
            }

            $context->enterBreadcrumbIndex($index);

            try {
                $validator->validate($item, $schema->unevaluatedItems, $context);
            } finally {
                $context->leaveBreadcrumb();
            }
        }
    }

    /**
     * Returns the list of array indices that have been evaluated by
     * prefixItems, items, contains, or any in-place applicator whose
     * annotations were merged into the context (R3-SPEC-002 / R3-SPEC-
     * 003 / R3-SPEC-004).
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<int>
     */
    private function getEvaluatedItemIndices(Schema $schema, array $data, ?ValidationContext $context): array
    {
        $dataCount = count($data);
        /** @var array<int, int> $evaluated */
        $evaluated = [];

        if (null !== $schema->prefixItems && [] !== $schema->prefixItems) {
            $prefixCount = count($schema->prefixItems);
            $upper = $prefixCount < $dataCount ? $prefixCount : $dataCount;
            for ($i = 0; $i < $upper; ++$i) {
                $evaluated[$i] = $i;
            }
        }

        if (null !== $schema->items) {
            $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;
            for ($i = $prefixCount; $i < $dataCount; ++$i) {
                $evaluated[$i] = $i;
            }
        }

        if (null !== $context) {
            foreach ($context->evaluatedItemIndices() as $index) {
                if ($index >= 0 && $index < $dataCount) {
                    $evaluated[$index] = $index;
                }
            }
        }

        return array_values($evaluated);
    }
}
