<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Registry;

use Duyler\OpenApi\Validator\Exception\UnknownValidatorException;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\EnumValidator;
use Duyler\OpenApi\Validator\SchemaValidator\FormatValidator;
use Duyler\OpenApi\Validator\SchemaValidator\IfThenElseValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NotValidator;
use Duyler\OpenApi\Validator\SchemaValidator\NumericRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ObjectLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PatternValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PrefixItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertyNamesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function array_key_exists;

use function assert;

final readonly class DefaultValidatorRegistry implements ValidatorRegistryInterface
{
    public readonly FormatRegistry $formatRegistry;

    private readonly array $validators;

    public function __construct(
        private readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::instance();
        $validators = $this->createValidators();
        foreach ($validators as $validator) {
            assert($validator instanceof SchemaValidatorInterface);
        }
        $this->validators = $validators;
    }

    #[Override]
    public function getValidator(string $type): SchemaValidatorInterface
    {
        if (false === array_key_exists($type, $this->validators)) {
            throw new UnknownValidatorException($type);
        }

        $validator = $this->validators[$type];
        assert($validator instanceof SchemaValidatorInterface);

        return $validator;
    }

    #[Override]
    public function getAllValidators(): iterable
    {
        return $this->validators;
    }

    private function createValidators(): array
    {
        $result = [
            AllOfValidator::class => new AllOfValidator($this->pool, $this->formatRegistry),
            AnyOfValidator::class => new AnyOfValidator($this->pool, $this->formatRegistry),
            ArrayLengthValidator::class => new ArrayLengthValidator($this->pool, $this->formatRegistry),
            ConstValidator::class => new ConstValidator($this->pool, $this->formatRegistry),
            ContainsRangeValidator::class => new ContainsRangeValidator($this->pool, $this->formatRegistry),
            ContainsValidator::class => new ContainsValidator($this->pool, $this->formatRegistry),
            DependentSchemasValidator::class => new DependentSchemasValidator($this->pool, $this->formatRegistry),
            EnumValidator::class => new EnumValidator($this->pool, $this->formatRegistry),
            FormatValidator::class => new FormatValidator($this->pool, $this->formatRegistry),
            IfThenElseValidator::class => new IfThenElseValidator($this->pool, $this->formatRegistry),
            ItemsValidator::class => new ItemsValidator($this->pool, $this->formatRegistry),
            NotValidator::class => new NotValidator($this->pool, $this->formatRegistry),
            NumericRangeValidator::class => new NumericRangeValidator($this->pool, $this->formatRegistry),
            ObjectLengthValidator::class => new ObjectLengthValidator($this->pool, $this->formatRegistry),
            OneOfValidator::class => new OneOfValidator($this->pool, $this->formatRegistry),
            PatternPropertiesValidator::class => new PatternPropertiesValidator($this->pool, $this->formatRegistry),
            PatternValidator::class => new PatternValidator($this->pool, $this->formatRegistry),
            PrefixItemsValidator::class => new PrefixItemsValidator($this->pool, $this->formatRegistry),
            PropertiesValidator::class => new PropertiesValidator($this->pool, $this->formatRegistry),
            PropertyNamesValidator::class => new PropertyNamesValidator($this->pool, $this->formatRegistry),
            RequiredValidator::class => new RequiredValidator($this->pool, $this->formatRegistry),
            StringLengthValidator::class => new StringLengthValidator($this->pool, $this->formatRegistry),
            TypeValidator::class => new TypeValidator($this->pool, $this->formatRegistry),
            UnevaluatedItemsValidator::class => new UnevaluatedItemsValidator($this->pool, $this->formatRegistry),
            UnevaluatedPropertiesValidator::class => new UnevaluatedPropertiesValidator($this->pool, $this->formatRegistry),
            AdditionalPropertiesValidator::class => new AdditionalPropertiesValidator($this->pool, $this->formatRegistry),
        ];

        return $result;
    }
}
