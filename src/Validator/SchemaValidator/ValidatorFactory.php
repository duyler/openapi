<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
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
        private readonly ?ValidatorRegistryInterface $registry = null,
    ) {}

    /**
     * @return array<class-string<SchemaValidatorInterface>, SchemaValidatorInterface>
     */
    public function createAll(): array
    {
        return [
            AllOfValidator::class => new AllOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            AnyOfValidator::class => new AnyOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ArrayLengthValidator::class => new ArrayLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ConstValidator::class => new ConstValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ContainsRangeValidator::class => new ContainsRangeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ContainsValidator::class => new ContainsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            DependentSchemasValidator::class => new DependentSchemasValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            DeprecatedValidator::class => new DeprecatedValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            EnumValidator::class => new EnumValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            FormatValidator::class => new FormatValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger),
            IfThenElseValidator::class => new IfThenElseValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ItemsValidator::class => new ItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            NotValidator::class => new NotValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            NumericRangeValidator::class => new NumericRangeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ObjectLengthValidator::class => new ObjectLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            OneOfValidator::class => new OneOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            PatternPropertiesValidator::class => new PatternPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            PatternValidator::class => new PatternValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            PrefixItemsValidator::class => new PrefixItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            PropertiesValidator::class => new PropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            PropertyNamesValidator::class => new PropertyNamesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            ReadOnlyWriteOnlyValidator::class => new ReadOnlyWriteOnlyValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            RequiredValidator::class => new RequiredValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            StringLengthValidator::class => new StringLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            TypeValidator::class => new TypeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            UnevaluatedItemsValidator::class => new UnevaluatedItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            UnevaluatedPropertiesValidator::class => new UnevaluatedPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
            AdditionalPropertiesValidator::class => new AdditionalPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry),
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
