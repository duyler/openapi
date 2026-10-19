<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\AdditionalPropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AllOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\AnyOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ConstValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentEncodingValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContentMediaTypeValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ContainsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DependentSchemasValidator;
use Duyler\OpenApi\Validator\SchemaValidator\DeprecatedValidator;
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
use Duyler\OpenApi\Validator\SchemaValidator\RefResolvingSchemaValidator;
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
use WeakMap;

use function array_filter;
use function array_values;
use function in_array;

/**
 * @psalm-type StatelessValidatorList = list<SchemaValidatorInterface>
 */
final class SchemaValidatorDependencies
{
    private const array CONTEXT_HANDLED_VALIDATORS = [
        ItemsValidator::class,
        PropertiesValidator::class,
        OneOfValidator::class,
    ];

    public readonly LoggerInterface $logger;
    public readonly ErrorFormatterInterface $errorFormatter;
    public readonly FormatRegistry $formatRegistry;
    public readonly BodyParser $bodyParser;

    /** @var WeakMap<OpenApiDocument, SchemaValidatorWithContext> */
    private WeakMap $rootSchemaValidators;

    /** @var array<class-string<SchemaValidatorInterface>, SchemaValidatorInterface>|null */
    private ?array $validators = null;

    /** @var StatelessValidatorList|null */
    private ?array $statelessList = null;

    private ?SchemaValidatorInterface $statelessRoot = null;

    public function __construct(
        public readonly ValidatorPool $pool,
        public readonly RefResolverInterface $refResolver,
        public readonly StatelessValidatorRegistry $statelessValidators,
        ?LoggerInterface $logger = null,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?ErrorFormatterInterface $errorFormatter = null,
        ?FormatRegistry $formatRegistry = null,
        ?BodyParser $bodyParser = null,
        public readonly bool $strictFormats = false,
        public readonly bool $reportDeprecated = false,
        public readonly RegexValidator $regexValidator = new RegexValidator(),
        public readonly PregExecutor $pregExecutor = new PregExecutor(),
        public readonly ?OpenApiDocument $document = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->errorFormatter = $errorFormatter ?? new SimpleFormatter();
        $this->formatRegistry = $formatRegistry ?? new FormatRegistry();
        $this->bodyParser = $bodyParser ?? new BodyParser(
            jsonParser: new JsonBodyParser(),
            formParser: new FormBodyParser(new QueryParser()),
            multipartParser: new MultipartBodyParser(),
            textParser: new TextBodyParser(),
            xmlParser: new XmlBodyParser(),
        );
        /** @var WeakMap<OpenApiDocument, SchemaValidatorWithContext> $rootSchemaValidators */
        $rootSchemaValidators = new WeakMap();
        $this->rootSchemaValidators = $rootSchemaValidators;
    }

    /**
     * @return StatelessValidatorList
     */
    public function createStatelessList(): array
    {
        return $this->statelessList ??= array_values(array_filter(
            $this->validators(),
            static fn(SchemaValidatorInterface $validator): bool => false === in_array(
                $validator::class,
                self::CONTEXT_HANDLED_VALIDATORS,
                true,
            ),
        ));
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    public function createFullList(): array
    {
        return array_values($this->validators());
    }

    public function rootStatelessValidator(): SchemaValidatorInterface
    {
        return $this->statelessRoot ??= $this->wrapRefResolution(
            new StatelessValidatorDispatcher($this->createFullList(), $this->pool),
        );
    }

    public function rootSchemaValidator(
        OpenApiDocument $document,
        ValidatorConfiguration $configuration,
    ): SchemaValidatorWithContext {
        if (isset($this->rootSchemaValidators[$document])) {
            /** @var SchemaValidatorWithContext $cached */
            $cached = $this->rootSchemaValidators[$document];

            return $cached;
        }

        $validator = new SchemaValidatorWithContext($document, $this, $configuration);
        $this->rootSchemaValidators[$document] = $validator;

        return $validator;
    }

    private function wrapRefResolution(SchemaValidatorInterface $validator): SchemaValidatorInterface
    {
        if (null === $this->document) {
            return $validator;
        }

        return new RefResolvingSchemaValidator($validator, $this->refResolver, $this->document);
    }

    /** @return array<class-string<SchemaValidatorInterface>, SchemaValidatorInterface> */
    private function validators(): array
    {
        return $this->validators ??= [
            AllOfValidator::class => new AllOfValidator($this),
            AnyOfValidator::class => new AnyOfValidator($this),
            ArrayLengthValidator::class => new ArrayLengthValidator($this),
            ConstValidator::class => new ConstValidator($this),
            ContainsValidator::class => new ContainsValidator($this),
            DependentSchemasValidator::class => new DependentSchemasValidator($this),
            DeprecatedValidator::class => new DeprecatedValidator($this),
            EnumValidator::class => new EnumValidator($this),
            FormatValidator::class => new FormatValidator($this),
            IfThenElseValidator::class => new IfThenElseValidator($this),
            ItemsValidator::class => new ItemsValidator($this),
            NotValidator::class => new NotValidator($this),
            NumericRangeValidator::class => new NumericRangeValidator($this),
            ObjectLengthValidator::class => new ObjectLengthValidator($this),
            OneOfValidator::class => new OneOfValidator($this),
            PatternPropertiesValidator::class => new PatternPropertiesValidator($this),
            PatternValidator::class => new PatternValidator($this),
            PrefixItemsValidator::class => new PrefixItemsValidator($this),
            PropertiesValidator::class => new PropertiesValidator($this),
            PropertyNamesValidator::class => new PropertyNamesValidator($this),
            ReadOnlyWriteOnlyValidator::class => new ReadOnlyWriteOnlyValidator($this),
            RequiredValidator::class => new RequiredValidator($this),
            StringLengthValidator::class => new StringLengthValidator($this),
            TypeValidator::class => new TypeValidator($this),
            UnevaluatedItemsValidator::class => new UnevaluatedItemsValidator($this),
            UnevaluatedPropertiesValidator::class => new UnevaluatedPropertiesValidator($this),
            AdditionalPropertiesValidator::class => new AdditionalPropertiesValidator($this),
            ContentEncodingValidator::class => new ContentEncodingValidator($this),
            ContentMediaTypeValidator::class => new ContentMediaTypeValidator($this),
        ];
    }
}
