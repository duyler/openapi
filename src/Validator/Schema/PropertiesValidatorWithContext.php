<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;

use function array_key_exists;
use function sprintf;

final readonly class PropertiesValidatorWithContext
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {}

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
        if (null === $schema->properties || [] === $schema->properties) {
            return;
        }

        $values = JsonShape::objectValues($data);

        if (null === $values) {
            return;
        }

        $errors = [];
        $rootValidator = $this->dependencies->rootSchemaValidator($this->document, $this->configuration);

        foreach ($schema->properties as $name => $propertySchema) {
            if (false === array_key_exists($name, $values)) {
                continue;
            }

            try {
                $allowNull = SchemaValueNormalizer::allowsNull($propertySchema, $context->nullableAsType, $context->dialectProfile);
                $value = SchemaValueNormalizer::normalize($values[$name], $allowNull);

                $childContext = $context->forkForBranch();
                $context->enterBreadcrumb($name);

                try {
                    if ($useDiscriminator) {
                        $rootValidator->validateWithContext($value, $propertySchema, $childContext);
                    } else {
                        $rootValidator->validateWithContextIgnoringDiscriminator($value, $propertySchema, $childContext);
                    }
                } finally {
                    $context->leaveBreadcrumb();
                }

                $context->markPropertyEvaluated($name);
                $context->mergeChildAnnotations($childContext);
            } catch (DiscriminatorMismatchException|
                InvalidDiscriminatorValueException|
                InvalidFormatException|
                MissingDiscriminatorPropertyException|
                UnknownDiscriminatorValueException $e
            ) {
                throw $e;
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            }
        }

        if ([] !== $errors) {
            throw new ValidationException(
                sprintf('Properties validation failed at %s', $context->breadcrumbs->currentPath()),
                errors: $errors,
            );
        }
    }
}
