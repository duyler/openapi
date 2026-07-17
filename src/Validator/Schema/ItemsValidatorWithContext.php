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
use Duyler\OpenApi\Validator\Exception\MissingDiscriminatorPropertyException;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;

use function count;
use function sprintf;

final readonly class ItemsValidatorWithContext
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {}

    public function validateWithContext(array $data, Schema $schema, ValidationContext $context, bool $useDiscriminator = true): void
    {
        if (null === $schema->items) {
            return;
        }

        $errors = [];
        $itemSchema = $schema->items;
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;
        $allowNull = $context->nullableAsType && ($itemSchema->nullable
            || SchemaValueNormalizer::typeIncludesNull($itemSchema->type));

        foreach ($data as $index => $item) {
            /** @var int $index */
            if ($index < $prefixCount) {
                continue;
            }

            try {
                $context->enterBreadcrumbIndex($index);

                try {
                    $normalizedItem = SchemaValueNormalizer::normalize($item, $allowNull);
                    $validator = new SchemaValidatorWithContext($this->document, $this->dependencies, $this->configuration);
                    $validator->validateWithContext($normalizedItem, $itemSchema, $context, $useDiscriminator);
                } finally {
                    $context->leaveBreadcrumb();
                }
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
                sprintf('Items validation failed at %s', $context->breadcrumbs->currentPath()),
                errors: $errors,
            );
        }
    }
}
