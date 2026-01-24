<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
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
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
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

final readonly class SchemaValidatorWithContext
{
    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly RefResolverInterface $refResolver,
        private readonly OpenApiDocument $document,
    ) {}

    /**
     * Validate data with ValidationContext for breadcrumb tracking
     */
    public function validate(array|int|string|float|bool $data, Schema $schema, bool $useDiscriminator = true): void
    {
        $context = ValidationContext::create($this->pool);

        if ($useDiscriminator && null !== $schema->discriminator) {
            $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool);
            $discriminatorValidator->validate($data, $schema, $this->document);
            return;
        }

        $this->validateInternal($data, $schema, $context);

        if (null !== $schema->properties && [] !== $schema->properties && is_array($data)) {
            $propertiesValidator = new PropertiesValidatorWithContext($this->pool, $this->refResolver, $this->document);
            $propertiesValidator->validateWithContext($data, $schema, $context);
        }

        if (null !== $schema->items && is_array($data)) {
            $itemsValidator = new ItemsValidatorWithContext($this->pool, $this->refResolver, $this->document);
            $itemsValidator->validateWithContext($data, $schema, $context);
        }
    }

    /**
     * Validate data with existing ValidationContext for breadcrumb tracking
     */
    public function validateWithContext(array|int|string|float|bool $data, Schema $schema, ValidationContext $context): void
    {
        if (null !== $schema->discriminator) {
            $discriminatorValidator = new DiscriminatorValidator($this->refResolver, $this->pool);
            $discriminatorValidator->validate($data, $schema, $this->document);
            return;
        }

        $this->validateInternal($data, $schema, $context);

        if (null !== $schema->properties && [] !== $schema->properties && is_array($data)) {
            $propertiesValidator = new PropertiesValidatorWithContext($this->pool, $this->refResolver, $this->document);
            $propertiesValidator->validateWithContext($data, $schema, $context);
        }

        if (null !== $schema->items && is_array($data)) {
            $itemsValidator = new ItemsValidatorWithContext($this->pool, $this->refResolver, $this->document);
            $itemsValidator->validateWithContext($data, $schema, $context);
        }
    }

    private function validateInternal(array|int|string|float|bool $data, Schema $schema, ?ValidationContext $context = null): void
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
        return [
            new TypeValidator($this->pool),
            new FormatValidator($this->pool, BuiltinFormats::create()),
            new StringLengthValidator($this->pool),
            new NumericRangeValidator($this->pool),
            new ArrayLengthValidator($this->pool),
            new ObjectLengthValidator($this->pool),
            new PatternValidator($this->pool),
            new AllOfValidator($this->pool),
            new AnyOfValidator($this->pool),
            new OneOfValidator($this->pool),
            new NotValidator($this->pool),
            new IfThenElseValidator($this->pool),
            new RequiredValidator($this->pool),
            new AdditionalPropertiesValidator($this->pool),
            new PropertyNamesValidator($this->pool),
            new UnevaluatedPropertiesValidator($this->pool),
            new PatternPropertiesValidator($this->pool),
            new DependentSchemasValidator($this->pool),
            new PrefixItemsValidator($this->pool),
            new UnevaluatedItemsValidator($this->pool),
            new ContainsValidator($this->pool),
            new ContainsRangeValidator($this->pool),
            new ConstValidator($this->pool),
            new EnumValidator($this->pool),
        ];
    }
}
