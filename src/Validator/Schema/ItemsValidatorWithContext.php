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
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\Exception\UnknownDiscriminatorValueException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\TypeFormatter;

use function count;
use function sprintf;

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

        // Boolean schema form per JSON Schema 2020-12 §4.3.2.
        if (true === $schema->items || false === $schema->items) {
            $this->validateBooleanItems($data, $schema, $context);

            return;
        }

        $errors = [];
        $itemSchema = $schema->items;
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;
        $allowNull = $context->nullableAsType && ($itemSchema->nullable
            || SchemaValueNormalizer::typeIncludesNull($itemSchema->type));
        $rootValidator = $this->dependencies->rootSchemaValidator($this->document, $this->configuration);

        foreach ($data as $index => $item) {
            /** @var int $index */
            if ($index < $prefixCount) {
                continue;
            }

            try {
                $context->enterBreadcrumbIndex($index);

                try {
                    $normalizedItem = SchemaValueNormalizer::normalize($item, $allowNull);
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

    /**
     * Handles boolean-form `items` per JSON Schema 2020-12 §4.3.2.
     *
     * `items: true` accepts every item (no-op); still marks each item as
     * evaluated so unevaluatedItems does not over-reject. `items: false`
     * rejects every item at index >= prefixItems count.
     *
     * @param array<array-key, mixed> $data
     */
    private function validateBooleanItems(array $data, Schema $schema, ValidationContext $context): void
    {
        $prefixCount = null !== $schema->prefixItems ? count($schema->prefixItems) : 0;

        if (true === $schema->items) {
            $dataCount = count($data);

            for ($i = $prefixCount; $i < $dataCount; ++$i) {
                $context->markItemEvaluated($i);
            }

            return;
        }

        $errors = [];
        $dataPath = $context->breadcrumbs->currentPath();
        $dataCount = count($data);

        for ($i = $prefixCount; $i < $dataCount; ++$i) {
            /** @var mixed $rejectedItem */
            $rejectedItem = $data[$i];

            $errors[] = new TypeMismatchError(
                expected: 'nothing (boolean schema false)',
                actual: TypeFormatter::format($rejectedItem),
                dataPath: $dataPath . '[' . $i . ']',
                schemaPath: '/items',
            );
        }

        if ([] !== $errors) {
            throw new ValidationException(
                'Items rejected by items: false',
                errors: $errors,
            );
        }
    }
}
