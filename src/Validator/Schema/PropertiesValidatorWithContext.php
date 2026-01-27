<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\DiscriminatorMismatchException;
use Duyler\OpenApi\Validator\Exception\InvalidDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;

use function array_key_exists;
use function count;
use function sprintf;

final readonly class PropertiesValidatorWithContext
{
    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
    ) {}

    public function validateWithContext(array $data, Schema $schema, ValidationContext $context): void
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
                $allowNull = $propertySchema->nullable && $context->nullableAsType;
                $value = SchemaValueNormalizer::normalize($data[$name], $allowNull);

                $propertyContext = $context->withBreadcrumb($name);

                $validator = new SchemaValidatorWithContext($this->pool, $this->refResolver, $this->document);
                $validator->validateWithContext($value, $propertySchema, $propertyContext);
            } catch (DiscriminatorMismatchException|
                InvalidDiscriminatorValueException|
                MissingDiscriminatorPropertyException|
                UnknownDiscriminatorValueException $e
            ) {
                throw $e;
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException(
                sprintf('Properties validation failed at %s', $context->breadcrumbs->currentPath()),
                errors: $errors,
            );
        }
    }
}
