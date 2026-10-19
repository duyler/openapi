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
use Duyler\OpenApi\Validator\Exception\NestedValidationError;
use Duyler\OpenApi\Validator\Exception\OneOfError;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\TypeFormatter;

use function implode;
use function is_array;
use function sprintf;

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

        if (null === $data && SchemaValueNormalizer::isNullableSchema($schema, $context->nullableAsType)) {
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

        $this->discriminatorValidator->validate($data, $schema, $this->document, $dataPath, $context);
    }

    /**
     * @param string|array<int, string|null>|null $type
     */
    private function formatSchemaType(string|array|null $type): string
    {
        return match (true) {
            null === $type => 'object',
            is_array($type) => implode('|', $type),
            default => $type,
        };
    }

    private function hasNullableSchema(array $oneOf): bool
    {
        return array_any($oneOf, fn(Schema $subSchema): bool => $subSchema->nullable
            || SchemaValueNormalizer::doesTypeIncludeNull($subSchema->type));
    }

    private function validateWithoutDiscriminator(mixed $data, array $oneOf, ValidationContext $context): void
    {
        $validCount = 0;
        $errors = [];

        $rootValidator = $this->dependencies->rootSchemaValidator($this->document, $this->configuration);

        foreach ($oneOf as $index => $subSchema) {
            if (false === $subSchema instanceof Schema) {
                continue;
            }

            $childContext = $context->forkForBranch();
            $schemaPath = sprintf('/oneOf/%d', $index);

            try {
                $allowNull = SchemaValueNormalizer::allowsNull($subSchema, $context->nullableAsType);
                $normalizedData = SchemaValueNormalizer::normalize($data, $allowNull);
                $rootValidator->validateWithContext($normalizedData, $subSchema, $childContext);
                ++$validCount;
                $context->mergeChildAnnotations($childContext);
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            } catch (InvalidDataTypeException) {
                $errors[] = new TypeMismatchError(
                    expected: $this->formatSchemaType($subSchema->type),
                    actual: TypeFormatter::format($data),
                    dataPath: $context->breadcrumbs->currentPath(),
                    schemaPath: $schemaPath,
                );
            } catch (ValidationException $e) {
                $branchErrors = $e->getErrors();

                if ([] === $branchErrors) {
                    $branchErrors = [new NestedValidationError(
                        dataPath: $context->breadcrumbs->currentPath(),
                        schemaPath: $schemaPath,
                        message: $e->getMessage(),
                    )];
                }

                $errors = [...$errors, ...$branchErrors];
            }
        }

        if (0 === $validCount) {
            throw new ValidationException(
                'Exactly one of schemas must match, but none did',
                errors: $errors,
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
