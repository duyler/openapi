<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\JsonShape;
use Duyler\OpenApi\Validator\TypeFormatter;
use Override;

use function array_diff_key;
use function array_flip;
use function array_values;
use function count;
use function is_bool;
use function is_int;

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

        $values = JsonShape::arrayValues($data);

        if (null === $values) {
            return;
        }

        if (is_bool($schema->unevaluatedItems) && $schema->unevaluatedItems) {
            $this->markAllItemsEvaluated($values, $context);

            return;
        }

        $evaluatedIndices = $this->getEvaluatedItemIndices($schema, $values, $context);
        /** @var list<int> $dataIndices */
        $dataIndices = array_keys($values);
        $unevaluatedIndices = array_values(array_diff_key(
            $dataIndices,
            array_flip($evaluatedIndices),
        ));

        if (false === $schema->unevaluatedItems) {
            $this->rejectUnevaluatedItems($values, $unevaluatedIndices, $this->getDataPath($context));

            return;
        }

        $this->validateUnevaluatedItems($values, $schema->unevaluatedItems, $unevaluatedIndices, $context);
    }

    /**
     * @param array<array-key, mixed> $data
     * @param list<int|string> $unevaluatedIndices
     */
    private function rejectUnevaluatedItems(array $data, array $unevaluatedIndices, string $dataPath): void
    {
        if ([] === $unevaluatedIndices) {
            return;
        }

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

    /**
     * @param array<array-key, mixed> $data
     * @param list<int|string> $unevaluatedIndices
     */
    private function validateUnevaluatedItems(array $data, Schema $unevaluatedItems, array $unevaluatedIndices, ?ValidationContext $context): void
    {
        $validator = $this->createSchemaValidator();
        $nullableAsType = $context?->nullableAsType ?? true;

        foreach ($unevaluatedIndices as $idx) {
            $index = (int) $idx;
            /** @var array-key|array<array-key, mixed> $item */
            $item = $data[$index];

            if (null === $context) {
                $context = ValidationContext::fromInit(new ValidationContextInit(pool: $this->pool(), nullableAsType: $nullableAsType));
            }

            $context->enterBreadcrumbIndex($index);

            try {
                $validator->validate($item, $unevaluatedItems, $context);
                $context->markItemEvaluated($index);
            } finally {
                $context->leaveBreadcrumb();
            }
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function markAllItemsEvaluated(array $data, ?ValidationContext $context): void
    {
        foreach (array_keys($data) as $index) {
            if (false === is_int($index)) {
                continue;
            }

            $context?->markItemEvaluated($index);
        }
    }

    /**
     * @param array<array-key, mixed> $data
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
