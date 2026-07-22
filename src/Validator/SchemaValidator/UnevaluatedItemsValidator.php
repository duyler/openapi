<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\TypeFormatter;
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

        if (true === $schema->unevaluatedItems) {
            return;
        }

        $evaluatedIndices = $this->getEvaluatedItemIndices($schema, $data, $context);
        /** @var list<int> $dataIndices */
        $dataIndices = array_keys($data);
        $unevaluatedIndices = array_values(array_diff_key(
            $dataIndices,
            array_flip($evaluatedIndices),
        ));

        if (false === $schema->unevaluatedItems) {
            if ([] === $unevaluatedIndices) {
                return;
            }

            $dataPath = $this->getDataPath($context);
            $errors = [];

            foreach ($unevaluatedIndices as $index) {
                /** @var array-key|array<array-key, mixed> $item */
                $item = $data[$index];
                $errors[] = new TypeMismatchError(
                    expected: 'nothing (boolean schema false)',
                    actual: TypeFormatter::format($item),
                    dataPath: $dataPath . '[' . $index . ']',
                    schemaPath: '/unevaluatedItems',
                );
            }

            throw new ValidationException(
                'Unevaluated items rejected by unevaluatedItems: false',
                errors: $errors,
            );
        }

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
     * Boolean-form `items: true` evaluates every index >= prefixItems count
     * (validation passes trivially); `items: false` does NOT register
     * evaluated indices because validation fails, so annotations do not
     * apply per JSON Schema 2020-12 §10.3.4.
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

        if (null !== $schema->items && false !== $schema->items) {
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
