<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\Error\ValidationContext;

use function sprintf;

abstract readonly class AbstractCompositionalValidator extends AbstractSchemaValidator
{
    /**
     * @param array<int, Schema> $schemas
     */
    protected function validateSchemas(
        array $schemas,
        mixed $data,
        ?ValidationContext $context,
        string $schemaType,
    ): ValidationResult {
        $nullableAsType = $context?->nullableAsType ?? true;
        $validCount = 0;
        $errors = [];
        $abstractErrors = [];

        foreach ($schemas as $subSchema) {
            try {
                $allowNull = $subSchema->nullable && $nullableAsType;
                $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                $validator = new SchemaValidator($this->pool);
                $validator->validate($normalizedData, $subSchema, $context);
                ++$validCount;
            } catch (InvalidDataTypeException $e) {
                $errors[] = new ValidationException(
                    sprintf('Invalid data type for %s schema: %s', $schemaType, $e->getMessage()),
                    previous: $e,
                );
            } catch (ValidationException $e) {
                $errors[] = $e;
                $abstractErrors = [...$abstractErrors, ...$e->getErrors()];
            } catch (AbstractValidationError $e) {
                $abstractErrors[] = $e;
            }
        }

        return new ValidationResult($validCount, $errors, $abstractErrors);
    }
}
