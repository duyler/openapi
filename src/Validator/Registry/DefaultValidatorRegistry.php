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

readonly class DefaultValidatorRegistry implements ValidatorRegistryInterface
{
    public readonly FormatRegistry $formatRegistry;

    private readonly array $validators;

    public function __construct(
        private readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::create();
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
            AllOfValidator::class => new AllOfValidator($this->pool),
            AnyOfValidator::class => new AnyOfValidator($this->pool),
            ArrayLengthValidator::class => new ArrayLengthValidator($this->pool),
            ConstValidator::class => new ConstValidator($this->pool),
            ContainsRangeValidator::class => new ContainsRangeValidator($this->pool),
            ContainsValidator::class => new ContainsValidator($this->pool),
            DependentSchemasValidator::class => new DependentSchemasValidator($this->pool),
            EnumValidator::class => new EnumValidator($this->pool),
            FormatValidator::class => new FormatValidator($this->pool, $this->formatRegistry),
            IfThenElseValidator::class => new IfThenElseValidator($this->pool),
            ItemsValidator::class => new ItemsValidator($this->pool),
            NotValidator::class => new NotValidator($this->pool),
            NumericRangeValidator::class => new NumericRangeValidator($this->pool),
            ObjectLengthValidator::class => new ObjectLengthValidator($this->pool),
            OneOfValidator::class => new OneOfValidator($this->pool),
            PatternPropertiesValidator::class => new PatternPropertiesValidator($this->pool),
            PatternValidator::class => new PatternValidator($this->pool),
            PrefixItemsValidator::class => new PrefixItemsValidator($this->pool),
            PropertiesValidator::class => new PropertiesValidator($this->pool),
            PropertyNamesValidator::class => new PropertyNamesValidator($this->pool),
            RequiredValidator::class => new RequiredValidator($this->pool),
            StringLengthValidator::class => new StringLengthValidator($this->pool),
            TypeValidator::class => new TypeValidator($this->pool),
            UnevaluatedItemsValidator::class => new UnevaluatedItemsValidator($this->pool),
            UnevaluatedPropertiesValidator::class => new UnevaluatedPropertiesValidator($this->pool),
            AdditionalPropertiesValidator::class => new AdditionalPropertiesValidator($this->pool),
        ];

        return $result;
    }
}
