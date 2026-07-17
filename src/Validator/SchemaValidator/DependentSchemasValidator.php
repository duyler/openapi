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

use function array_key_exists;
use function is_array;
use function sprintf;

final readonly class DependentSchemasValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->dependentSchemas && [] !== $schema->dependentSchemas;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->dependentSchemas || [] === $schema->dependentSchemas) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $nullableAsType = $context?->nullableAsType ?? true;

        foreach ($schema->dependentSchemas as $propertyName => $dependentSchema) {
            if (array_key_exists($propertyName, $data)) {
                try {
                    $allowNull = $nullableAsType && ($dependentSchema->nullable
                        || SchemaValueNormalizer::typeIncludesNull($dependentSchema->type));
                    $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                    $validator = $this->createSchemaValidator();
                    $validator->validate($normalizedData, $dependentSchema, $context);
                } catch (InvalidDataTypeException $e) {
                    $dataPath = $this->getDataPath($context);

                    throw new ValidationException(
                        sprintf('Dependent schema for property "%s" has invalid data type: %s', $propertyName, $e->getMessage()),
                        previous: $e,
                        errors: [
                            new TypeMismatchError(
                                expected: $this->formatSchemaType($dependentSchema->type, 'object'),
                                actual: TypeFormatter::format($data),
                                dataPath: $dataPath,
                                schemaPath: '/dependentSchemas/' . $propertyName,
                            ),
                        ],
                    );
                } catch (InvalidFormatException $e) {
                    throw $e;
                } catch (AbstractValidationError $e) {
                    $dataPath = $this->getDataPath($context);

                    throw new ValidationException(
                        sprintf('Dependent schema for property "%s" validation failed: %s', $propertyName, $e->getMessage()),
                        previous: $e,
                        errors: [$e],
                    );
                } catch (ValidationException $e) {
                    $dataPath = $this->getDataPath($context);
                    $errors = $e->getErrors();

                    if ([] === $errors) {
                        $errors = [
                            new NestedValidationError(
                                dataPath: $dataPath,
                                schemaPath: '/dependentSchemas/' . $propertyName,
                                message: $e->getMessage(),
                            ),
                        ];
                    }

                    throw new ValidationException(
                        sprintf('Dependent schema for property "%s" validation failed', $propertyName),
                        previous: $e,
                        errors: $errors,
                    );
                }
            }
        }
    }
}
