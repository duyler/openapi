<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\NestedValidationError;
use Duyler\OpenApi\Validator\Exception\TooManyErrorsError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\SchemaValueNormalizer;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\SchemaValidator\Internal\BranchOutcome;
use Duyler\OpenApi\Validator\TypeFormatter;

use function array_values;
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
        $failedCount = 0;
        $errors = [];
        $dataPath = $this->getDataPath($context);

        foreach ($schemas as $index => $subSchema) {
            $outcome = $this->validateBranch($data, $subSchema, $context, $schemaType, $index);

            if ($outcome->matched) {
                ++$validCount;
                continue;
            }

            ++$failedCount;

            foreach ($outcome->errors as $error) {
                $errors[] = $error;

                if (self::MAX_COMPOSITION_ERRORS <= count($errors)) {
                    $errors[] = new TooManyErrorsError(
                        max: self::MAX_COMPOSITION_ERRORS,
                        dataPath: $dataPath,
                    );

                    return new ValidationResult($validCount, $errors, $failedCount);
                }
            }
        }

        return new ValidationResult($validCount, $errors, $failedCount);
    }

    private function validateBranch(
        mixed $data,
        Schema $subSchema,
        ?ValidationContext $context,
        string $schemaType,
        int $index,
    ): BranchOutcome {
        $schemaPath = sprintf('/%s/%d', $schemaType, $index);

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

            return new BranchOutcome(matched: true, errors: []);
        } catch (InvalidDataTypeException) {
            return new BranchOutcome(
                matched: false,
                errors: [new TypeMismatchError(
                    expected: $this->formatSchemaType($subSchema->type, 'object'),
                    actual: TypeFormatter::format($data),
                    dataPath: $this->getDataPath($context),
                    schemaPath: $schemaPath,
                )],
            );
        } catch (ValidationException $e) {
            $errors = array_values($e->getErrors());

            if ([] === $errors) {
                $errors = [new NestedValidationError(
                    dataPath: $this->getDataPath($context),
                    schemaPath: $schemaPath,
                    message: $e->getMessage(),
                )];
            }

            return new BranchOutcome(matched: false, errors: $errors);
        } catch (AbstractValidationError $e) {
            return new BranchOutcome(matched: false, errors: [$e]);
        }
    }

    /**
     * @return array<array-key, mixed>|int|string|float|bool|null
     */
    private function normalizeForBranch(mixed $data, Schema $subSchema, ?ValidationContext $context): array|int|string|float|bool|null
    {
        $nullableAsType = $context?->nullableAsType ?? true;
        $allowNull = $nullableAsType && ($subSchema->nullable
            || SchemaValueNormalizer::doesTypeIncludeNull($subSchema->type));

        return SchemaValueNormalizer::normalize($data, $allowNull);
    }
}
