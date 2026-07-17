<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Registry\ValidatorRegistryInterface;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
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
        private readonly RegexValidator $regexValidator = new RegexValidator(),
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    /**
     * @return array<class-string<SchemaValidatorInterface>, SchemaValidatorInterface>
     */
    public function createAll(): array
    {
        return [
            AllOfValidator::class => new AllOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            AnyOfValidator::class => new AnyOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ArrayLengthValidator::class => new ArrayLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ConstValidator::class => new ConstValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ContainsValidator::class => new ContainsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            DependentSchemasValidator::class => new DependentSchemasValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            DeprecatedValidator::class => new DeprecatedValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            EnumValidator::class => new EnumValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            FormatValidator::class => new FormatValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger),
            IfThenElseValidator::class => new IfThenElseValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ItemsValidator::class => new ItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            NotValidator::class => new NotValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            NumericRangeValidator::class => new NumericRangeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ObjectLengthValidator::class => new ObjectLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            OneOfValidator::class => new OneOfValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            PatternPropertiesValidator::class => new PatternPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            PatternValidator::class => new PatternValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            PrefixItemsValidator::class => new PrefixItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            PropertiesValidator::class => new PropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            PropertyNamesValidator::class => new PropertyNamesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ReadOnlyWriteOnlyValidator::class => new ReadOnlyWriteOnlyValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            RequiredValidator::class => new RequiredValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            StringLengthValidator::class => new StringLengthValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            TypeValidator::class => new TypeValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            UnevaluatedItemsValidator::class => new UnevaluatedItemsValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            UnevaluatedPropertiesValidator::class => new UnevaluatedPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            AdditionalPropertiesValidator::class => new AdditionalPropertiesValidator($this->pool, $this->formatRegistry, $this->strictFormats, $this->logger, $this->reportDeprecated, $this->eventDispatcher, $this->registry, $this->regexValidator, $this->pregExecutor),
            ContentEncodingValidator::class => new ContentEncodingValidator(),
            ContentMediaTypeValidator::class => new ContentMediaTypeValidator(pregExecutor: $this->pregExecutor),
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
