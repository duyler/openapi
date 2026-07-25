<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\NestedValidationError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\ItemValidationState;
use Duyler\OpenApi\Validator\TypeFormatter;
use Override;

use function is_array;
use function sprintf;
use function count;
use function is_bool;

final readonly class ItemsValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->items;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->items) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        if (is_bool($schema->items)) {
            $this->validateBooleanItems($data, $schema, $context);

            return;
        }

        /** @var Schema $itemsSchema */
        $itemsSchema = $schema->items;
        $this->validateSchemaItems($data, $itemsSchema, $schema->prefixItems, $context);
    }

    /**
     * @param array<int, mixed>             $data
     * @param list<Schema>|null             $prefixItems
     */
    private function validateSchemaItems(array $data, Schema $itemsSchema, ?array $prefixItems, ?ValidationContext $context): void
    {
        $prefixCount = null !== $prefixItems ? count($prefixItems) : 0;
        $nullableAsType = $context?->nullableAsType ?? true;
        $allowNull = $nullableAsType && ($itemsSchema->nullable
            || SchemaValueNormalizer::typeIncludesNull($itemsSchema->type));

        $state = new ItemValidationState(
            itemsSchema: $itemsSchema,
            validator: $this->createSchemaValidator(),
            allowNull: $allowNull,
            nullableAsType: $nullableAsType,
            context: $context,
        );

        foreach ($data as $index => $item) {
            if ($index < $prefixCount) {
                continue;
            }

            /** @var int $index */
            $this->validateOneItem($item, $index, $state);
        }
    }

    private function validateOneItem(mixed $item, int $index, ItemValidationState $state): void
    {
        try {
            $normalizedItem = SchemaValueNormalizer::normalize($item, $state->allowNull);

            if (null === $state->context) {
                $state->context = ValidationContext::create(pool: $this->pool(), nullableAsType: $state->nullableAsType);
            }

            $state->context->enterBreadcrumbIndex($index);

            try {
                $state->validator->validate($normalizedItem, $state->itemsSchema, $state->context);
                $state->context->markItemEvaluated($index);
            } finally {
                $state->context->leaveBreadcrumb();
            }
        } catch (InvalidDataTypeException $e) {
            $dataPath = $this->getDataPath($state->context);

            throw new ValidationException(
                sprintf('Item at index %d has invalid data type: %s', $index, $e->getMessage()),
                previous: $e,
                errors: [
                    new TypeMismatchError(
                        expected: $this->formatSchemaType($state->itemsSchema->type),
                        actual: TypeFormatter::format($item),
                        dataPath: $dataPath . '[' . $index . ']',
                        schemaPath: '/items',
                    ),
                ],
            );
        } catch (InvalidFormatException $e) {
            throw $e;
        } catch (AbstractValidationError $e) {
            throw new ValidationException(
                sprintf('Item at index %d validation failed: %s', $index, $e->getMessage()),
                previous: $e,
                errors: [$e],
            );
        } catch (ValidationException $e) {
            $dataPath = $this->getDataPath($state->context);
            $errors = $e->getErrors();

            if ([] === $errors) {
                $errors = [
                    new NestedValidationError(
                        dataPath: $dataPath . '[' . $index . ']',
                        schemaPath: '/items',
                        message: $e->getMessage(),
                    ),
                ];
            }

            throw new ValidationException(
                sprintf('Item at index %d validation failed', $index),
                previous: $e,
                errors: $errors,
            );
        }
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function validateBooleanItems(array $data, Schema $schema, ?ValidationContext $context): void
    {
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;

        if ($schema->items) {
            $dataCount = count($data);

            if (null !== $context) {
                for ($i = $prefixCount; $i < $dataCount; ++$i) {
                    $context->markItemEvaluated($i);
                }
            }

            return;
        }

        $dataPath = $this->getDataPath($context);
        $errors = [];
        $dataCount = count($data);

        for ($i = $prefixCount; $i < $dataCount; ++$i) {
            /** @var mixed $rejectedItem */
            $rejectedItem = $data[$i];

            $errors[] = new TypeMismatchError(
                expected: 'nothing (boolean schema false)',
                actual: TypeFormatter::format($rejectedItem),
                dataPath: $dataPath . '[' . $i . ']',
                schemaPath: '/items',
            );
        }

        if ([] !== $errors) {
            throw new ValidationException(
                'Items rejected by items: false',
                errors: $errors,
            );
        }
    }
}
