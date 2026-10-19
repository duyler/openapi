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
use Duyler\OpenApi\Validator\SchemaValidator\Internal\BooleanItemsValidator;

use function count;
use function sprintf;
use function is_bool;

final readonly class ItemsValidatorWithContext
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
        if (null === $schema->items) {
            return;
        }

        if (is_bool($schema->items)) {
            BooleanItemsValidator::validate($data, $schema, $context);

            return;
        }

        $errors = [];
        $itemSchema = $schema->items;
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;
        $allowNull = SchemaValueNormalizer::allowsNull($itemSchema, $context->nullableAsType);
        $rootValidator = $this->dependencies->rootSchemaValidator($this->document, $this->configuration);

        foreach ($data as $index => $arrayItem) {
            /** @var int $index */
            if ($index < $prefixCount) {
                continue;
            }

            try {
                $context->enterBreadcrumbIndex($index);

                try {
                    $normalizedItem = SchemaValueNormalizer::normalize($arrayItem, $allowNull);
                    if ($useDiscriminator) {
                        $rootValidator->validateWithContext($normalizedItem, $itemSchema, $context);
                    } else {
                        $rootValidator->validateWithContextIgnoringDiscriminator($normalizedItem, $itemSchema, $context);
                    }
                    $context->markItemEvaluated($index);
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
                sprintf('Items validation failed at %s', $context->breadcrumbs->currentPath()),
                errors: $errors,
            );
        }
    }
}
