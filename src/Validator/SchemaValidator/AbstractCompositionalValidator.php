<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\TooManyErrorsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\Error\ValidationContext;

use function count;
use function sprintf;

abstract readonly class AbstractCompositionalValidator extends AbstractSchemaValidator
{
    private const int MAX_COMPOSITION_ERRORS = 20;

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
        $dataPath = $this->getDataPath($context);

        foreach ($schemas as $subSchema) {
            try {
                $allowNull = $nullableAsType && ($subSchema->nullable
                    || SchemaValueNormalizer::typeIncludesNull($subSchema->type));
                $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                $validator = $this->createSchemaValidator();
                $validator->validate($normalizedData, $subSchema, $context);
                ++$validCount;
            } catch (InvalidDataTypeException $e) {
                $errors[] = new ValidationException(
                    sprintf('Invalid data type for %s schema: %s', $schemaType, $e->getMessage()),
                    previous: $e,
                );
            } catch (ValidationException $e) {
                $errors[] = $e;
                foreach ($e->getErrors() as $error) {
                    $abstractErrors[] = $error;
                    if (self::MAX_COMPOSITION_ERRORS <= count($abstractErrors)) {
                        $abstractErrors[] = new TooManyErrorsError(
                            max: self::MAX_COMPOSITION_ERRORS,
                            dataPath: $dataPath,
                        );

                        return new ValidationResult($validCount, $errors, $abstractErrors);
                    }
                }
            } catch (AbstractValidationError $e) {
                $abstractErrors[] = $e;
                if (self::MAX_COMPOSITION_ERRORS <= count($abstractErrors)) {
                    $abstractErrors[] = new TooManyErrorsError(
                        max: self::MAX_COMPOSITION_ERRORS,
                        dataPath: $dataPath,
                    );

                    return new ValidationResult($validCount, $errors, $abstractErrors);
                }
            }
        }

        return new ValidationResult($validCount, $errors, $abstractErrors);
    }
}
