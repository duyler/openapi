<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\DiscriminatorDataError;
use Duyler\OpenApi\Validator\Exception\InvalidDataTypeException;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\ValidationException;

use function is_array;

final readonly class OneOfValidatorWithContext
{
    private readonly DiscriminatorValidator $discriminatorValidator;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {
        $this->discriminatorValidator = new DiscriminatorValidator($this->dependencies, $this->configuration);
    }

    public function validateWithContext(mixed $data, Schema $schema, ValidationContext $context): void
    {
        $this->validate($data, $schema, $context, true);
    }

    public function validateWithContextIgnoringDiscriminator(mixed $data, Schema $schema, ValidationContext $context): void
    {
        $this->validate($data, $schema, $context, false);
    }

    private function validate(mixed $data, Schema $schema, ValidationContext $context, bool $useDiscriminator): void
    {
        $oneOf = $schema->oneOf;

        if (null === $oneOf) {
            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator) {
            $this->validateWithDiscriminator($data, $schema, $context);
            return;
        }

        $this->validateWithoutDiscriminator($data, $oneOf, $context);
    }

    private function validateWithDiscriminator(mixed $data, Schema $schema, ValidationContext $context): void
    {
        if (
            null === $data
            && null !== $schema->oneOf
            && $this->hasNullableSchema($schema->oneOf)
            && $context->nullableAsType
        ) {
            return;
        }

        if (false === is_array($data)) {
            throw new ValidationException(
                'Discriminator validation failed: data must be an object',
                errors: [
                    new DiscriminatorDataError(
                        dataPath: $context->breadcrumbs->currentPath(),
                        schemaPath: '/oneOf',
                    ),
                ],
            );
        }

        $dataPath = $context->breadcrumbs->currentPath();

        $this->discriminatorValidator->validate($data, $schema, $this->document, $dataPath);
    }

    private function hasNullableSchema(array $oneOf): bool
    {
        return array_any($oneOf, fn(Schema $subSchema): bool => $subSchema->nullable);
    }

    private function validateWithoutDiscriminator(mixed $data, array $oneOf, ValidationContext $context): void
    {
        $validCount = 0;
        $errors = [];
        $abstractErrors = [];

        $rootValidator = $this->dependencies->rootSchemaValidator($this->document, $this->configuration);

        foreach ($oneOf as $subSchema) {
            if (false === $subSchema instanceof Schema) {
                continue;
            }

            $childContext = $context->forkForBranch();

            try {
                $allowNull = $context->nullableAsType && ($subSchema->nullable
                    || SchemaValueNormalizer::typeIncludesNull($subSchema->type));
                $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                $rootValidator->validateWithContext($normalizedData, $subSchema, $childContext);
                ++$validCount;
                $context->mergeChildAnnotations($childContext);
            } catch (AbstractValidationError $e) {
                $abstractErrors[] = $e;
            } catch (InvalidDataTypeException) {
                continue;
            } catch (ValidationException $e) {
                $errors[] = new ValidationException(
                    message: 'Invalid data for oneOf schema: ' . $e->getMessage(),
                    previous: $e,
                    errors: $e->getErrors(),
                );
            }
        }

        if (0 === $validCount) {
            $allErrors = $abstractErrors;
            foreach ($errors as $error) {
                $allErrors = [...$allErrors, ...$error->getErrors()];
            }

            throw new ValidationException(
                'Exactly one of schemas must match, but none did',
                errors: $allErrors,
            );
        }

        if ($validCount > 1) {
            throw new ValidationException(
                'Data matches multiple schemas, but should match exactly one',
                errors: [
                    new OneOfError(
                        dataPath: $context->breadcrumbs->currentPath(),
                        schemaPath: '/oneOf',
                    ),
                ],
            );
        }
    }
}
