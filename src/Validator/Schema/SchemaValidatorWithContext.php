<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\EnumValidator;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use Duyler\OpenApi\Validator\SchemaValidator\IfThenElseValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ObjectLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;

use function count;
use function is_array;

class SchemaValidatorWithContext
{
    private const int MAX_SCHEMA_DEPTH = 64;

    private ?array $validatorsCache = null;
    private readonly FormatRegistry $formatRegistry;

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
        ?FormatRegistry $formatRegistry = null,
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
    }

    public function validate(array|int|string|float|bool|null $data, Schema $schema, bool $useDiscriminator = true): void
    {
        $context = ValidationContext::create($this->pool, $this->nullableAsType, $this->emptyArrayStrategy);

        $schema = $this->resolveRef($schema);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $oneOfValidator = new OneOfValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->formatRegistry);
            $oneOfValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->validateInternal($data, $schema, $context);

            $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool, $this->formatRegistry);
            $discriminatorValidator->validate($data, $schema, $this->document);

            $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
            return;
        }

        $this->validateInternal($data, $schema, $context);

        $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    public function validateWithContext(array|int|string|float|bool|null $data, Schema $schema, ValidationContext $context, bool $useDiscriminator = true): void
    {
        $this->checkDepth($context);

        $context = $context->withIncrementedDepth();

        $schema = $this->resolveRef($schema);

        if ($useDiscriminator && null !== $schema->discriminator && null !== $schema->oneOf) {
            $oneOfValidator = new OneOfValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->formatRegistry);
            $oneOfValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
            return;
        }

        if ($useDiscriminator && null !== $schema->discriminator && null !== $data) {
            $this->validateInternal($data, $schema, $context);

            $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool, $this->formatRegistry);
            $discriminatorValidator->validate($data, $schema, $this->document);

            $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
            return;
        }

        $this->validateInternal($data, $schema, $context);

        $this->validatePropertiesAndItems($data, $schema, $context, $useDiscriminator);
    }

    private function checkDepth(ValidationContext $context): void
    {
        if ($context->depth >= self::MAX_SCHEMA_DEPTH) {
            throw new SchemaDepthExceededException(self::MAX_SCHEMA_DEPTH);
        }
    }

    private function validatePropertiesAndItems(
        array|int|string|float|bool|null $data,
        Schema $schema,
        ValidationContext $context,
        bool $useDiscriminator,
    ): void {
        if (null !== $schema->properties && [] !== $schema->properties && is_array($data)) {
            $propertiesValidator = new PropertiesValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->formatRegistry);
            $propertiesValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
        }

        if (null !== $schema->items && is_array($data)) {
            $itemsValidator = new ItemsValidatorWithContext($this->pool, $this->refResolver, $this->document, $this->formatRegistry);
            $itemsValidator->validateWithContext($data, $schema, $context, $useDiscriminator);
        }
    }

    private function resolveRef(Schema $schema): Schema
    {
        if (null === $schema->ref) {
            return $schema;
        }

        return $this->refResolver->resolveSchemaWithOverride($schema, $this->document);
    }

    private function validateInternal(array|int|string|float|bool|null $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $errors = [];

        foreach ($this->getValidators() as $validator) {
            try {
                /** @var SchemaValidatorInterface $validator */
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

    private function getValidators(): array
    {
        if (null !== $this->validatorsCache) {
            return $this->validatorsCache;
        }

        $this->validatorsCache = [
            new TypeValidator($this->pool, $this->formatRegistry),
            new FormatValidator($this->pool, $this->formatRegistry),
            new StringLengthValidator($this->pool, $this->formatRegistry),
            new NumericRangeValidator($this->pool, $this->formatRegistry),
            new ArrayLengthValidator($this->pool, $this->formatRegistry),
            new ObjectLengthValidator($this->pool, $this->formatRegistry),
            new PatternValidator($this->pool, $this->formatRegistry),
            new AllOfValidator($this->pool, $this->formatRegistry),
            new AnyOfValidator($this->pool, $this->formatRegistry),
            new NotValidator($this->pool, $this->formatRegistry),
            new IfThenElseValidator($this->pool, $this->formatRegistry),
            new RequiredValidator($this->pool, $this->formatRegistry),
            new AdditionalPropertiesValidator($this->pool, $this->formatRegistry),
            new PropertyNamesValidator($this->pool, $this->formatRegistry),
            new UnevaluatedPropertiesValidator($this->pool, $this->formatRegistry),
            new PatternPropertiesValidator($this->pool, $this->formatRegistry),
            new DependentSchemasValidator($this->pool, $this->formatRegistry),
            new PrefixItemsValidator($this->pool, $this->formatRegistry),
            new UnevaluatedItemsValidator($this->pool, $this->formatRegistry),
            new ContainsValidator($this->pool, $this->formatRegistry),
            new ContainsRangeValidator($this->pool, $this->formatRegistry),
            new ConstValidator($this->pool, $this->formatRegistry),
            new EnumValidator($this->pool, $this->formatRegistry),
        ];

        return $this->validatorsCache;
    }
}
