<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Closure;

use function array_filter;
use function array_values;
use function in_array;

use const ARRAY_FILTER_USE_KEY;

final readonly class ValidatorFactory
{
    private const array CONTEXT_HANDLED_VALIDATORS = [
        ItemsValidator::class,
        PropertiesValidator::class,
        OneOfValidator::class,
    ];

    /**
     * @param array<string, Closure(ValidatorDependencies): SchemaValidatorInterface> $customValidators
     */
    public function __construct(
        private readonly ValidatorDependencies $dependencies,
        private readonly array $customValidators = [],
    ) {}

    /**
     * @return array<string, SchemaValidatorInterface>
     */
    public function createAll(): array
    {
        $builtin = [
            AllOfValidator::class,
            AnyOfValidator::class,
            ArrayLengthValidator::class,
            ConstValidator::class,
            ContainsValidator::class,
            DependentSchemasValidator::class,
            DeprecatedValidator::class,
            EnumValidator::class,
            FormatValidator::class,
            IfThenElseValidator::class,
            ItemsValidator::class,
            NotValidator::class,
            NumericRangeValidator::class,
            ObjectLengthValidator::class,
            OneOfValidator::class,
            PatternPropertiesValidator::class,
            PatternValidator::class,
            PrefixItemsValidator::class,
            PropertiesValidator::class,
            PropertyNamesValidator::class,
            ReadOnlyWriteOnlyValidator::class,
            RequiredValidator::class,
            StringLengthValidator::class,
            TypeValidator::class,
            UnevaluatedItemsValidator::class,
            UnevaluatedPropertiesValidator::class,
            AdditionalPropertiesValidator::class,
            ContentEncodingValidator::class,
            ContentMediaTypeValidator::class,
        ];

        $validators = [];
        foreach ($builtin as $class) {
            $validators[$class] = new $class($this->dependencies);
        }

        foreach ($this->customValidators as $keyword => $factory) {
            $validators[$keyword] = $factory($this->dependencies);
        }

        return $validators;
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    public function createStatelessList(): array
    {
        return array_values(array_filter(
            $this->createAll(),
            fn(int|string $class): bool => !in_array($class, self::CONTEXT_HANDLED_VALIDATORS, true),
            ARRAY_FILTER_USE_KEY,
        ));
    }
}
