<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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

    public function __construct(
        private readonly ValidatorPool $pool,
        private readonly FormatRegistry $formatRegistry,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $reportDeprecated = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * @return array<class-string<SchemaValidatorInterface>, SchemaValidatorInterface>
     */
    public function createAll(): array
    {
        return [
            AllOfValidator::class => new AllOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            AnyOfValidator::class => new AnyOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ArrayLengthValidator::class => new ArrayLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ConstValidator::class => new ConstValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ContainsRangeValidator::class => new ContainsRangeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ContainsValidator::class => new ContainsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            DependentSchemasValidator::class => new DependentSchemasValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            DeprecatedValidator::class => new DeprecatedValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            EnumValidator::class => new EnumValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            FormatValidator::class => new FormatValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger),
            IfThenElseValidator::class => new IfThenElseValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ItemsValidator::class => new ItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            NotValidator::class => new NotValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            NumericRangeValidator::class => new NumericRangeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ObjectLengthValidator::class => new ObjectLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            OneOfValidator::class => new OneOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            PatternPropertiesValidator::class => new PatternPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            PatternValidator::class => new PatternValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            PrefixItemsValidator::class => new PrefixItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            PropertiesValidator::class => new PropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            PropertyNamesValidator::class => new PropertyNamesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ReadOnlyWriteOnlyValidator::class => new ReadOnlyWriteOnlyValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            RequiredValidator::class => new RequiredValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            StringLengthValidator::class => new StringLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            TypeValidator::class => new TypeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            UnevaluatedItemsValidator::class => new UnevaluatedItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            UnevaluatedPropertiesValidator::class => new UnevaluatedPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            AdditionalPropertiesValidator::class => new AdditionalPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher),
            ContentEncodingValidator::class => new ContentEncodingValidator(),
            ContentMediaTypeValidator::class => new ContentMediaTypeValidator(),
        ];
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
