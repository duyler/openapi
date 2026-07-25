<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\NestedValidationError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\TypeFormatter;
use Throwable;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;

use function sprintf;

/**
 * Wraps exceptions thrown while validating a single array item
 * (items / prefixItems) into a ValidationException anchored at the
 * item's data-path and schema-path. Eliminates the duplicated
 * four-branch catch chain that previously existed in both
 * {@see ItemsValidator}
 * and {@see PrefixItemsValidator}.
 *
 * @internal
 */
trait ItemValidationExceptionTrait
{
    /**
     * @param non-empty-string $schemaPath e.g. '/items' or '/prefixItems/{i}'
     *
     * @throws Throwable always re-throws either the original exception (for
     *                   InvalidFormatException and any unrecognised type) or
     *                   a new ValidationException wrapping the failure.
     */
    private function wrapItemValidationException(
        Throwable $e,
        int $index,
        mixed $item,
        Schema $itemsSchema,
        string $schemaPath,
        ?ValidationContext $context,
    ): never {
        if ($e instanceof InvalidDataTypeException) {
            $dataPath = $this->getDataPath($context);

            throw new ValidationException(
                sprintf('Item at index %d has invalid data type: %s', $index, $e->getMessage()),
                previous: $e,
                errors: [
                    new TypeMismatchError(
                        expected: $this->formatSchemaType($itemsSchema->type),
                        actual: TypeFormatter::format($item),
                        dataPath: $dataPath . '[' . $index . ']',
                        schemaPath: $schemaPath,
                    ),
                ],
            );
        }

        if ($e instanceof InvalidFormatException) {
            throw $e;
        }

        if ($e instanceof AbstractValidationError) {
            throw new ValidationException(
                sprintf('Item at index %d validation failed: %s', $index, $e->getMessage()),
                previous: $e,
                errors: [$e],
            );
        }

        if ($e instanceof ValidationException) {
            $dataPath = $this->getDataPath($context);
            $errors = $e->getErrors();

            if ([] === $errors) {
                $errors = [
                    new NestedValidationError(
                        dataPath: $dataPath . '[' . $index . ']',
                        schemaPath: $schemaPath,
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

        throw $e;
    }
}
