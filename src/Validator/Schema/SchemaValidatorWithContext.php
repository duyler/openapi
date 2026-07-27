<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Schema\Internal\CompositionResolver;
use Duyler\OpenApi\Validator\Schema\Internal\PropertiesAndItemsDispatcher;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorMode;
use WeakMap;

final readonly class SchemaValidatorWithContext
{
    private OneOfValidatorWithContext $oneOfValidator;
    private DiscriminatorValidator $discriminatorValidator;
    private PropertiesValidatorWithContext $propertiesValidator;
    private ItemsValidatorWithContext $itemsValidator;
    private CompositionResolver $compositionResolver;
    private PropertiesAndItemsDispatcher $dispatcher;

    /** @var WeakMap<Schema, Schema> */
    private WeakMap $resolvedCache;

    /** @var WeakMap<Schema, list<SchemaValidatorInterface>> */
    private WeakMap $applicableStatelessValidators;

    public function __construct(
        private OpenApiDocument $document,
        private SchemaValidatorDependencies $dependencies,
        private ValidatorConfiguration $configuration = new ValidatorConfiguration(),
    ) {
        $this->oneOfValidator = new OneOfValidatorWithContext($this->document, $this->dependencies, $this->configuration);
        $this->discriminatorValidator = new DiscriminatorValidator($this->dependencies, $this->configuration);
        $this->propertiesValidator = new PropertiesValidatorWithContext($this->document, $this->dependencies, $this->configuration);
        $this->itemsValidator = new ItemsValidatorWithContext($this->document, $this->dependencies, $this->configuration);
        /** @var WeakMap<Schema, Schema> $resolvedCache */
        $resolvedCache = new WeakMap();
        $this->resolvedCache = $resolvedCache;
        /** @var WeakMap<Schema, list<SchemaValidatorInterface>> $applicableStatelessValidators */
        $applicableStatelessValidators = new WeakMap();
        $this->applicableStatelessValidators = $applicableStatelessValidators;

        $this->compositionResolver = new CompositionResolver($this->document, $this->dependencies, $this->resolvedCache);
        $this->dispatcher = new PropertiesAndItemsDispatcher(
            $this->dependencies,
            $this->propertiesValidator,
            $this->itemsValidator,
            $this->applicableStatelessValidators,
        );
    }

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

    public function validateWithContext(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context): void
    {
        $context->incrementDepth();

        try {
            $this->doValidate($data, $schema, $context, true);
        } finally {
            $context->decrementDepth();
        }
    }

    public function validateWithContextIgnoringDiscriminator(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context): void
    {
        $context->incrementDepth();

        try {
            $this->doValidate($data, $schema, $context, false);
        } finally {
            $context->decrementDepth();
        }
    }

    private function doValidate(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context, bool $useDiscriminator): void
    {
        $schema = $this->resolveRef($schema);
        /** @var WeakMap<Schema, true> $visited */
        $visited = new WeakMap();
        $schema = $this->compositionResolver->resolveCompositionRefs($schema, $visited);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $this->oneOfValidator->validateWithContext($data, $schema, $context);

            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->dispatcher->validateInternal($data, $schema, $context);
            $this->discriminatorValidator->validate($data, $schema, $this->document, '/', $context);
            $this->dispatcher->dispatchPropertiesAndItems($data, $schema, $context, $useDiscriminator);

            return;
        }

        if (null === $schema->discriminator && null !== $schema->oneOf) {
            $this->oneOfValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
        }

        $this->dispatcher->validateInternal($data, $schema, $context);
        $this->dispatcher->dispatchPropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    private function resolveRef(Schema $schema): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        return $this->dependencies->refResolver->resolveSchemaWithOverride($schema, $this->document);
    }
}
