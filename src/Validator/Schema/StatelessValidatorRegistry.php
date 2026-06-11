<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Schema;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsRangeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
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
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class StatelessValidatorRegistry
{
    /** @var list<SchemaValidatorInterface> */
    private readonly array $validators;

    public function __construct(
        ValidatorPool $pool,
        FormatRegistry $formatRegistry,
        bool $reportDeprecated = false,
        LoggerInterface $logger = new NullLogger(),
        ?EventDispatcherInterface $eventDispatcher = null,
    ) {
        $this->validators = [
            new TypeValidator($pool, $formatRegistry),
            new FormatValidator($pool, $formatRegistry),
            new StringLengthValidator($pool, $formatRegistry),
            new NumericRangeValidator($pool, $formatRegistry),
            new ArrayLengthValidator($pool, $formatRegistry),
            new ObjectLengthValidator($pool, $formatRegistry),
            new PatternValidator($pool, $formatRegistry),
            new AllOfValidator($pool, $formatRegistry),
            new AnyOfValidator($pool, $formatRegistry),
            new NotValidator($pool, $formatRegistry),
            new IfThenElseValidator($pool, $formatRegistry),
            new RequiredValidator($pool, $formatRegistry),
            new AdditionalPropertiesValidator($pool, $formatRegistry),
            new PropertyNamesValidator($pool, $formatRegistry),
            new ReadOnlyWriteOnlyValidator($pool, $formatRegistry),
            new UnevaluatedPropertiesValidator($pool, $formatRegistry),
            new PatternPropertiesValidator($pool, $formatRegistry),
            new DependentSchemasValidator($pool, $formatRegistry),
            new PrefixItemsValidator($pool, $formatRegistry),
            new UnevaluatedItemsValidator($pool, $formatRegistry),
            new ContainsValidator($pool, $formatRegistry),
            new ContainsRangeValidator($pool, $formatRegistry),
            new ConstValidator($pool, $formatRegistry),
            new EnumValidator($pool, $formatRegistry),
            new DeprecatedValidator($pool, $formatRegistry, reportDeprecated: $reportDeprecated, logger: $logger, eventDispatcher: $eventDispatcher),
            new ContentEncodingValidator(),
            new ContentMediaTypeValidator(),
        ];
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    public function getValidators(): array
    {
        return $this->validators;
    }
}
