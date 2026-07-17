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

    public function validateWithContext(array $data, Schema $schema, ValidationContext $context): void
    {
        $this->validate($data, $schema, $context, true);
    }

    public function validateWithContextIgnoringDiscriminator(array $data, Schema $schema, ValidationContext $context): void
    {
        $this->validate($data, $schema, $context, false);
    }

    private function validate(array $data, Schema $schema, ValidationContext $context, bool $useDiscriminator): void
    {
        if (null === $schema->properties || [] === $schema->properties) {
            return;
        }

        $errors = [];

        foreach ($schema->properties as $name => $propertySchema) {
            if (false === array_key_exists($name, $data)) {
                continue;
            }

            try {
                $allowNull = $context->nullableAsType && ($propertySchema->nullable
                    || SchemaValueNormalizer::typeIncludesNull($propertySchema->type));
                $value = SchemaValueNormalizer::normalize($data[$name], $allowNull);

                $context->enterBreadcrumb($name);

                try {
                    $rootValidator = $this->dependencies->rootSchemaValidator($this->document, $this->configuration);
                    if ($useDiscriminator) {
                        $rootValidator->validateWithContext($value, $propertySchema, $context);
                    } else {
                        $rootValidator->validateWithContextIgnoringDiscriminator($value, $propertySchema, $context);
                    }
                } finally {
                    $context->leaveBreadcrumb();
                }
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
