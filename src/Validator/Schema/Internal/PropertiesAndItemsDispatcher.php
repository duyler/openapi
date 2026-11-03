<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Schema\ItemsValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\PropertiesValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\JsonShape;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use WeakMap;

/** @internal */
final class PropertiesAndItemsDispatcher
{
    public function __construct(
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly PropertiesValidatorWithContext $propertiesValidator,
        private readonly ItemsValidatorWithContext $itemsValidator,
        /** @var WeakMap<Schema, list<SchemaValidatorInterface>> */
        private WeakMap $applicableStatelessValidators,
    ) {}

    public function dispatchPropertiesAndItems(
        mixed $data,
        Schema $schema,
        ValidationContext $context,
        bool $useDiscriminator,
    ): void {
        if (null !== $schema->properties && [] !== $schema->properties && JsonShape::isObjectInstance($data)) {
            if ($useDiscriminator) {
                $this->propertiesValidator->validateWithContext($data, $schema, $context);
            } else {
                $this->propertiesValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
            }
        }

        if (null !== $schema->items && JsonShape::isArrayInstance($data)) {
            if ($useDiscriminator) {
                $this->itemsValidator->validateWithContext($data, $schema, $context);
            } else {
                $this->itemsValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
            }
        }
    }

    public function validateInternal(mixed $data, Schema $schema, ValidationContext $context): void
    {
        $errors = [];

        /** @var list<SchemaValidatorInterface> $validators */
        $validators = $this->applicableStatelessValidators[$schema]
            ?? $this->computeApplicableStatelessValidators($schema);
        $this->applicableStatelessValidators[$schema] = $validators;

        foreach ($validators as $validator) {
            try {
                $validator->validate($data, $schema, $context);
            } catch (InvalidFormatException $e) {
                throw $e;
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            }
        }

        if ([] !== $errors) {
            throw new ValidationException(
                'Schema validation failed',
                errors: $errors,
            );
        }
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    private function computeApplicableStatelessValidators(Schema $schema): array
    {
        return ApplicableValidators::filter(
            $this->dependencies->statelessValidators->getValidators(),
            $schema,
        );
    }
}
