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
        $validCount = 0;
        $errors = [];
        $abstractErrors = [];
        $dataPath = $this->getDataPath($context);

        foreach ($schemas as $subSchema) {
            try {
                $normalizedData = $this->normalizeForBranch($data, $subSchema, $context);
                $validator = $this->createSchemaValidator();

                if (null !== $context) {
                    $childContext = $context->forkForBranch();
                    $validator->validate($normalizedData, $subSchema, $childContext);
                    $context->mergeChildAnnotations($childContext);
                } else {
                    $validator->validate($normalizedData, $subSchema, null);
                }

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

    /**
     * @return array<array-key, mixed>|int|string|float|bool|null
     */
    private function normalizeForBranch(mixed $data, Schema $subSchema, ?ValidationContext $context): array|int|string|float|bool|null
    {
        $nullableAsType = $context?->nullableAsType ?? true;
        $allowNull = $nullableAsType && ($subSchema->nullable
            || SchemaValueNormalizer::typeIncludesNull($subSchema->type));

        return SchemaValueNormalizer::normalize($data, $allowNull);
    }
}
