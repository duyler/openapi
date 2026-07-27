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
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use WeakMap;

use function array_filter;
use function array_values;
use function is_array;

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
        array|int|string|float|bool|null $data,
        Schema $schema,
        ValidationContext $context,
        bool $useDiscriminator,
    ): void {
        if (null !== $schema->properties && [] !== $schema->properties && is_array($data)) {
            if ($useDiscriminator) {
                $this->propertiesValidator->validateWithContext($data, $schema, $context);
            } else {
                $this->propertiesValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
            }
        }

        if (null !== $schema->items && is_array($data)) {
            if ($useDiscriminator) {
                $this->itemsValidator->validateWithContext($data, $schema, $context);
            } else {
                $this->itemsValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
            }
        }
    }

    public function validateInternal(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context): void
    {
        $errors = [];

        if ($this->applicableStatelessValidators->offsetExists($schema)) {
            /** @var list<SchemaValidatorInterface> $validators */
            $validators = $this->applicableStatelessValidators[$schema];
        } else {
            $validators = $this->computeApplicableStatelessValidators($schema);
            $this->applicableStatelessValidators[$schema] = $validators;
        }

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
        $all = $this->dependencies->statelessValidators->getValidators();

        /** @var list<SchemaValidatorInterface> $filtered */
        $filtered = array_values(array_filter(
            $all,
            static function (SchemaValidatorInterface $v) use ($schema): bool {
                return false === ($v instanceof KeywordApplicable) || $v->isApplicable($schema);
            },
        ));

        return $filtered;
    }
}
