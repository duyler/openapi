<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorMode;

use function count;
use function is_array;

final class SchemaValidatorWithContext
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {}

    public function validate(array|int|string|float|bool|null $data, Schema $schema, ?ValidatorMode $mode = null): void
    {
        $context = ValidationContext::create(
            $this->dependencies->pool,
            $this->dependencies->errorFormatter,
            $this->configuration->nullableAsType,
            $this->configuration->emptyArrayStrategy,
            $mode,
        );

        $this->doValidate($data, $schema, $context, true);
    }

    public function validateWithContext(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context, bool $useDiscriminator = true): void
    {
        $context = $context->withIncrementedDepth();

        $this->doValidate($data, $schema, $context, $useDiscriminator);
    }

    private function doValidate(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context, bool $useDiscriminator): void
    {
        $schema = $this->resolveRef($schema);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $oneOfValidator = new OneOfValidatorWithContext($this->document, $this->dependencies, $this->configuration);
            $oneOfValidator->validateWithContext($data, $schema, $context, $useDiscriminator);

            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->validateInternal($data, $schema, $context);

            $discriminatorValidator = new DiscriminatorValidator($this->dependencies, $this->configuration);
            $discriminatorValidator->validate($data, $schema, $this->document);

            $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);

            return;
        }

        $this->validateInternal($data, $schema, $context);

        $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    private function validatePropertiesAndItems(
        array|int|string|float|bool|null $data,
        Schema $schema,
        ValidationContext $context,
        bool $useDiscriminator,
    ): void {
        if (null !== $schema->properties && [] !== $schema->properties && is_array($data)) {
            $propertiesValidator = new PropertiesValidatorWithContext($this->document, $this->dependencies, $this->configuration);
            $propertiesValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
        }

        if (null !== $schema->items && is_array($data)) {
            $itemsValidator = new ItemsValidatorWithContext($this->document, $this->dependencies, $this->configuration);
            $itemsValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
        }
    }

    private function resolveRef(Schema $schema): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        return $this->dependencies->refResolver->resolveSchemaWithOverride($schema, $this->document);
    }

    private function validateInternal(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context): void
    {
        $errors = [];

        foreach ($this->dependencies->statelessValidators->getValidators() as $validator) {
            try {
                $validator->validate($data, $schema, $context);
            } catch (AbstractValidationError $e) {
                $errors[] = $e;
            }
        }

        if (count($errors) > 0) {
            throw new ValidationException(
                'Schema validation failed',
                errors: $errors,
            );
        }
    }
}
