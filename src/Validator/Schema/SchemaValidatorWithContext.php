<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\InvalidFormatException;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\SchemaValidator\KeywordApplicable;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorMode;
use WeakMap;

use function array_filter;
use function array_values;
use function assert;
use function count;
use function is_array;

final class SchemaValidatorWithContext
{
    private readonly OneOfValidatorWithContext $oneOfValidator;
    private readonly DiscriminatorValidator $discriminatorValidator;
    private readonly PropertiesValidatorWithContext $propertiesValidator;
    private readonly ItemsValidatorWithContext $itemsValidator;

    /** @var WeakMap<Schema, Schema> */
    private WeakMap $resolvedCache;

    /** @var WeakMap<Schema, list<SchemaValidatorInterface>> */
    private WeakMap $applicableStatelessValidators;

    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly SchemaValidatorDependencies $dependencies,
        private readonly ValidatorConfiguration $configuration = new ValidatorConfiguration(),
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
        $schema = $this->resolveCompositionRefs($schema, $visited);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $this->oneOfValidator->validateWithContext($data, $schema, $context);

            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->validateInternal($data, $schema, $context);

            $this->discriminatorValidator->validate($data, $schema, $this->document);

            $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);

            return;
        }

        $this->validateInternal($data, $schema, $context);

        if (null === $schema->discriminator && null !== $schema->oneOf) {
            $this->oneOfValidator->validateWithContextIgnoringDiscriminator($data, $schema, $context);
        }

        $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    private function validatePropertiesAndItems(
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

    private function resolveRef(Schema $schema): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        return $this->dependencies->refResolver->resolveSchemaWithOverride($schema, $this->document);
    }

    /**
     * Pre-resolves $ref in allOf/anyOf/oneOf subschemas so that stateless
     * composition validators (which have no document context) see real
     * constraints instead of opaque {$ref: '...'} stubs.
     *
     * oneOf/anyOf arrays are left untouched when the schema has a discriminator:
     * DiscriminatorValidator relies on the raw $ref pointers in those arrays
     * for implicit title-based mapping fallback.
     *
     * allOf is always resolved because discriminator selection happens before
     * allOf merge and allOf never participates in discriminator mapping.
     *
     * Recurses into nested composition arrays to handle specs where a
     * resolved subschema itself contains further composition with $ref.
     *
     * @param WeakMap<Schema, true> $visited identity-based cycle guard
     *
     * @throws SchemaDepthExceededException if recursion exceeds MAX_DEPTH
     */
    private function resolveCompositionRefs(Schema $schema, WeakMap $visited): Schema
    {
        if (isset($this->resolvedCache[$schema])) {
            $cached = $this->resolvedCache[$schema];
            assert(null !== $cached);

            return $cached;
        }

        if (ValidationContext::MAX_DEPTH <= count($visited)) {
            throw new SchemaDepthExceededException(ValidationContext::MAX_DEPTH);
        }

        if ($visited->offsetExists($schema)) {
            $this->resolvedCache[$schema] = $schema;

            return $schema;
        }

        $visited[$schema] = true;

        $allOf = $this->resolveCompositionArray($schema->allOf, $visited);

        $hasDiscriminator = null !== $schema->discriminator;

        $anyOf = $hasDiscriminator
            ? $schema->anyOf
            : $this->resolveCompositionArray($schema->anyOf, $visited);

        $oneOf = $hasDiscriminator
            ? $schema->oneOf
            : $this->resolveCompositionArray($schema->oneOf, $visited);

        if ($allOf === $schema->allOf && $anyOf === $schema->anyOf && $oneOf === $schema->oneOf) {
            $this->resolvedCache[$schema] = $schema;

            return $schema;
        }

        $resolved = $schema->withOverrides(
            allOf: $allOf,
            anyOf: $anyOf,
            oneOf: $oneOf,
        );

        $this->resolvedCache[$schema] = $resolved;

        return $resolved;
    }

    /**
     * Resolves $ref in a single composition array and recurses into each
     * subschema's own composition keywords.
     *
     * Subschemas whose resolved target carries a discriminator are left as
     * $ref stubs: they must be validated via SchemaValidatorWithContext for
     * discriminator routing, which stateless composition validators never do.
     * Resolving them in place would expose discriminator + oneOf to stateless
     * validators that cannot route by discriminator and would error out.
     *
     * @param list<Schema>|null      $schemas
     * @param WeakMap<Schema, true>  $visited
     *
     * @return list<Schema>|null
     */
    private function resolveCompositionArray(?array $schemas, WeakMap $visited): ?array
    {
        if (null === $schemas) {
            return null;
        }

        $result = [];
        $changed = false;

        foreach ($schemas as $subSchema) {
            $resolved = $subSchema;

            if (null !== $subSchema->ref) {
                $candidate = $this->dependencies->refResolver->resolveSchemaWithOverride(
                    $subSchema,
                    $this->document,
                );

                if (null === $candidate->discriminator) {
                    $resolved = $candidate;
                    $changed = true;
                }
            }

            $recursivelyResolved = $this->resolveCompositionRefs($resolved, $visited);

            if ($recursivelyResolved !== $resolved) {
                $changed = true;
                $resolved = $recursivelyResolved;
            }

            $result[] = $resolved;
        }

        return $changed ? $result : $schemas;
    }

    private function validateInternal(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context): void
    {
        $errors = [];

        if (isset($this->applicableStatelessValidators[$schema])) {
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
                return !$v instanceof KeywordApplicable || $v->isApplicable($schema);
            },
        ));

        return $filtered;
    }
}
