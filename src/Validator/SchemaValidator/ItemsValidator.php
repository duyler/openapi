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
use Duyler\OpenApi\Validator\TypeFormatter;
use Override;

use function is_array;
use function sprintf;
use function count;

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

        // Boolean schema form per JSON Schema 2020-12 §4.3.2.
        // `items: true` accepts every item (no-op); still marks each item as
        // evaluated so unevaluatedItems does not over-reject. `items: false`
        // rejects every item at index >= prefixItems count.
        if (true === $schema->items || false === $schema->items) {
            $this->validateBooleanItems($data, $schema, $context);

            return;
        }

        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;
        $validator = $this->createSchemaValidator();
        $nullableAsType = $context?->nullableAsType ?? true;
        $allowNull = $nullableAsType && ($schema->items->nullable
            || SchemaValueNormalizer::typeIncludesNull($schema->items->type));

        foreach ($data as $index => $item) {
            if ($index < $prefixCount) {
                continue;
            }

            try {
                $normalizedItem = SchemaValueNormalizer::normalize($item, $allowNull);

                if (null === $context) {
                    $context = ValidationContext::create(pool: $this->pool(), nullableAsType: $nullableAsType);
                }

                $context->enterBreadcrumbIndex($index);

                try {
                    $validator->validate($normalizedItem, $schema->items, $context);
                    /** @var int $index */
                    $context->markItemEvaluated($index);
                } finally {
                    $context->leaveBreadcrumb();
                }
            } catch (InvalidDataTypeException $e) {
                $dataPath = $this->getDataPath($context);

                throw new ValidationException(
                    sprintf('Item at index %d has invalid data type: %s', $index, $e->getMessage()),
                    previous: $e,
                    errors: [
                        new TypeMismatchError(
                            expected: $this->formatSchemaType($schema->items->type),
                            actual: TypeFormatter::format($item),
                            dataPath: $dataPath . '[' . $index . ']',
                            schemaPath: '/items',
                        ),
                    ],
                );
            } catch (InvalidFormatException $e) {
                throw $e;
            } catch (AbstractValidationError $e) {
                $dataPath = $this->getDataPath($context);

                throw new ValidationException(
                    sprintf('Item at index %d validation failed: %s', $index, $e->getMessage()),
                    previous: $e,
                    errors: [$e],
                );
            } catch (ValidationException $e) {
                $dataPath = $this->getDataPath($context);
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
    }

    /**
     * Handles boolean-form `items` per JSON Schema 2020-12 §4.3.2.
     *
     * `items: true` accepts every item (no-op); still marks each item as
     * evaluated so unevaluatedItems does not over-reject. `items: false`
     * rejects every item at index >= prefixItems count.
     *
     * @param array<array-key, mixed> $data
     */
    private function validateBooleanItems(array $data, Schema $schema, ?ValidationContext $context): void
    {
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;

        if (true === $schema->items) {
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
