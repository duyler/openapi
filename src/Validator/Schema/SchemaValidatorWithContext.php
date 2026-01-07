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
            new \Duyler\OpenApi\Validator\SchemaValidator\TypeValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\FormatValidator($this->pool, \Duyler\OpenApi\Validator\Format\BuiltinFormats::create()),
            new \Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\ObjectLengthValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\PatternValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\NotValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\IfThenElseValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\ContainsRangeValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\ConstValidator($this->pool),
            new \Duyler\OpenApi\Validator\SchemaValidator\EnumValidator($this->pool),
        ];
    }
}
