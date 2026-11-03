<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Schema\Internal\KeywordValidatorAdapter;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentRequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DynamicRefValidator;
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
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;

use function spl_object_id;

/**
 * Immutable registry of schema keyword validators keyed by keyword name.
 *
 * Insertion order is dispatch order. Replacing a keyword keeps the position of
 * its slot; a keyword that is not registered yet is appended to the end, after
 * the built-in set (including the unevaluated* validators). Built-in
 * validators serving several keywords are registered under each keyword they
 * serve; all() de-duplicates them by instance, keeping the position of the
 * first keyword slot, so each built-in validator runs exactly once.
 */
final readonly class KeywordValidatorRegistry
{
    /**
     * @param array<string, KeywordValidatorInterface> $validators
     */
    public function __construct(
        private readonly array $validators = [],
    ) {}

    public static function buildIn(SchemaValidatorDependencies $dependencies): self
    {
        $arrayLength = new KeywordValidatorAdapter(new ArrayLengthValidator($dependencies));
        $numericRange = new KeywordValidatorAdapter(new NumericRangeValidator($dependencies));
        $objectLength = new KeywordValidatorAdapter(new ObjectLengthValidator($dependencies));
        $readOnlyWriteOnly = new KeywordValidatorAdapter(new ReadOnlyWriteOnlyValidator($dependencies));
        $stringLength = new KeywordValidatorAdapter(new StringLengthValidator($dependencies));

        return new self()
            ->with('allOf', new KeywordValidatorAdapter(new AllOfValidator($dependencies)))
            ->with('anyOf', new KeywordValidatorAdapter(new AnyOfValidator($dependencies)))
            ->with('minItems', $arrayLength)
            ->with('maxItems', $arrayLength)
            ->with('uniqueItems', $arrayLength)
            ->with('const', new KeywordValidatorAdapter(new ConstValidator($dependencies)))
            ->with('contains', new KeywordValidatorAdapter(new ContainsValidator($dependencies)))
            ->with('dependentRequired', new KeywordValidatorAdapter(new DependentRequiredValidator($dependencies)))
            ->with('dependentSchemas', new KeywordValidatorAdapter(new DependentSchemasValidator($dependencies)))
            ->with('deprecated', new KeywordValidatorAdapter(new DeprecatedValidator($dependencies)))
            ->with('dynamicRef', new KeywordValidatorAdapter(new DynamicRefValidator($dependencies)))
            ->with('enum', new KeywordValidatorAdapter(new EnumValidator($dependencies)))
            ->with('format', new KeywordValidatorAdapter(new FormatValidator($dependencies)))
            ->with('if', new KeywordValidatorAdapter(new IfThenElseValidator($dependencies)))
            ->with('items', new KeywordValidatorAdapter(new ItemsValidator($dependencies)))
            ->with('not', new KeywordValidatorAdapter(new NotValidator($dependencies)))
            ->with('minimum', $numericRange)
            ->with('maximum', $numericRange)
            ->with('exclusiveMinimum', $numericRange)
            ->with('exclusiveMaximum', $numericRange)
            ->with('multipleOf', $numericRange)
            ->with('minProperties', $objectLength)
            ->with('maxProperties', $objectLength)
            ->with('oneOf', new KeywordValidatorAdapter(new OneOfValidator($dependencies)))
            ->with('patternProperties', new KeywordValidatorAdapter(new PatternPropertiesValidator($dependencies)))
            ->with('pattern', new KeywordValidatorAdapter(new PatternValidator($dependencies)))
            ->with('prefixItems', new KeywordValidatorAdapter(new PrefixItemsValidator($dependencies)))
            ->with('properties', new KeywordValidatorAdapter(new PropertiesValidator($dependencies)))
            ->with('propertyNames', new KeywordValidatorAdapter(new PropertyNamesValidator($dependencies)))
            ->with('readOnly', $readOnlyWriteOnly)
            ->with('writeOnly', $readOnlyWriteOnly)
            ->with('required', new KeywordValidatorAdapter(new RequiredValidator($dependencies)))
            ->with('minLength', $stringLength)
            ->with('maxLength', $stringLength)
            ->with('type', new KeywordValidatorAdapter(new TypeValidator($dependencies)))
            ->with('unevaluatedItems', new KeywordValidatorAdapter(new UnevaluatedItemsValidator($dependencies)))
            ->with('unevaluatedProperties', new KeywordValidatorAdapter(new UnevaluatedPropertiesValidator($dependencies)))
            ->with('additionalProperties', new KeywordValidatorAdapter(new AdditionalPropertiesValidator($dependencies)))
            ->with('contentEncoding', new KeywordValidatorAdapter(new ContentEncodingValidator($dependencies)))
            ->with('contentMediaType', new KeywordValidatorAdapter(new ContentMediaTypeValidator($dependencies)));
    }

    public function with(string $keyword, KeywordValidatorInterface $validator): self
    {
        $validators = $this->validators;
        $validators[$keyword] = $validator;

        return new self($validators);
    }

    public function withBase(self $base): self
    {
        $merged = $base->validators;

        foreach ($this->validators as $keyword => $validator) {
            $merged[$keyword] = $validator;
        }

        return new self($merged);
    }

    public function get(string $keyword): ?KeywordValidatorInterface
    {
        return $this->validators[$keyword] ?? null;
    }

    public function has(string $keyword): bool
    {
        return isset($this->validators[$keyword]);
    }

    /**
     * @return list<KeywordValidatorInterface>
     */
    public function all(): array
    {
        $validators = [];
        $seen = [];

        foreach ($this->validators as $validator) {
            $objectId = spl_object_id($validator);

            if (isset($seen[$objectId])) {
                continue;
            }

            $seen[$objectId] = true;
            $validators[] = $validator;
        }

        return $validators;
    }
}
