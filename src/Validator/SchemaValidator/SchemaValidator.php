<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class SchemaValidator implements SchemaValidatorInterface
{
    public readonly FormatRegistry $formatRegistry;

    public function __construct(
        private readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
    ) {
        $this->formatRegistry = $formatRegistry ?? BuiltinFormats::create();
    }

    #[Override]
    public function validate(array|int|string|float|bool $data, Schema $schema, ?ValidationContext $context = null): void
    {
        $validators = [
            new TypeValidator($this->pool),
            new FormatValidator($this->pool, $this->formatRegistry),
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
            new PropertiesValidator($this->pool),
            new AdditionalPropertiesValidator($this->pool),
            new PropertyNamesValidator($this->pool),
            new UnevaluatedPropertiesValidator($this->pool),
            new PatternPropertiesValidator($this->pool),
            new DependentSchemasValidator($this->pool),
            new ItemsValidator($this->pool),
            new PrefixItemsValidator($this->pool),
            new UnevaluatedItemsValidator($this->pool),
            new ContainsValidator($this->pool),
            new ContainsRangeValidator($this->pool),
            new ConstValidator($this->pool),
            new EnumValidator($this->pool),
        ];

        foreach ($validators as $validator) {
            $validator->validate($data, $schema, $context);
        }
    }
}
