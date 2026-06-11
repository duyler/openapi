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
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
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
use Duyler\OpenApi\Validator\SchemaValidator\ReadOnlyWriteOnlyValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RequiredValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\StringLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\TypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\UnevaluatedPropertiesValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function array_key_exists;

use function assert;

final readonly class DefaultValidatorRegistry implements ValidatorRegistryInterface
{
    public readonly FormatRegistry $formatRegistry;

    private readonly array $validators;

    public function __construct(
        private readonly ValidatorPool $pool,
        ?FormatRegistry $formatRegistry = null,
        private readonly bool $strictFormats = false,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly bool $reportDeprecated = false,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
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

        return $result;
    }
}
