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

use function count;
use function is_array;
use function sprintf;

final readonly class PrefixItemsValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->prefixItems && [] !== $schema->prefixItems;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->prefixItems || [] === $schema->prefixItems) {
            return;
        }

        if (false === is_array($data) || false === array_is_list($data)) {
            return;
        }

        $state = new ItemValidationState(
            itemsSchema: $schema,
            validator: $this->createSchemaValidator(),
            allowNull: false,
            nullableAsType: $context?->nullableAsType ?? true,
            context: $context,
        );

        $count = min(count($data), count($schema->prefixItems));

        for ($i = 0; $i < $count; ++$i) {
            $this->validatePrefixItemAt($data[$i], $i, $state);
        }
    }

    private function validatePrefixItemAt(mixed $item, int $index, ItemValidationState $state): void
    {
        $subSchema = $state->itemsSchema->prefixItems[$index] ?? null;

        if (null === $subSchema) {
            return;
        }

        try {
            $allowNull = $state->nullableAsType && ($subSchema->nullable
                || SchemaValueNormalizer::typeIncludesNull($subSchema->type));
            $value = SchemaValueNormalizer::normalize($item, $allowNull);

            if (null === $state->context) {
                $state->context = ValidationContext::create(pool: $this->pool(), nullableAsType: $state->nullableAsType);
            }

            $state->context->enterBreadcrumbIndex($index);

            try {
                $state->validator->validate($value, $subSchema, $state->context);
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
                        expected: $this->formatSchemaType($subSchema->type),
                        actual: TypeFormatter::format($item),
                        dataPath: $dataPath . '[' . $index . ']',
                        schemaPath: '/prefixItems/' . $index,
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
                        schemaPath: '/prefixItems/' . $index,
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
