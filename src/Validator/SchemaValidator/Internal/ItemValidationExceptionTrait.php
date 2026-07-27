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

use function sprintf;

/** @internal */
trait ItemValidationExceptionTrait
{
    /**
     * @param non-empty-string $schemaPath
     * @throws Throwable
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
