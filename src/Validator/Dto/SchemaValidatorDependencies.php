<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\ContentDecoderInterface;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\NativeContentDecoder;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Schema\Internal\KeywordValidatorAdapter;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorInterface;
use Duyler\OpenApi\Validator\Schema\KeywordValidatorRegistry;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Schema\RefResolverInterface;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\ItemsValidator;
use Duyler\OpenApi\Validator\SchemaValidator\OneOfValidator;
use Duyler\OpenApi\Validator\SchemaValidator\PropertiesValidator;
use Duyler\OpenApi\Validator\SchemaValidator\RefResolvingSchemaValidator;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WeakMap;

use function array_filter;
use function array_values;
use function assert;
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

    /** @var StatelessValidatorList|null */
    private ?array $statelessList = null;

    private ?SchemaValidatorInterface $statelessRoot = null;

    private ?KeywordValidatorRegistry $keywordValidators = null;

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
        public readonly bool $contentAssertionsEnabled = false,
        public readonly ContentDecoderInterface $contentDecoder = new NativeContentDecoder(),
        public readonly int $maxDecodedContentBytes = ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES,
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
            $this->createFullList(),
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
        $validators = [];

        foreach ($this->keywordValidators()->all() as $keywordValidator) {
            $validators[] = $this->schemaValidator($keywordValidator);
        }

        return $validators;
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

    private function keywordValidators(): KeywordValidatorRegistry
    {
        return $this->keywordValidators ??= KeywordValidatorRegistry::buildIn($this);
    }

    private function schemaValidator(KeywordValidatorInterface $keywordValidator): SchemaValidatorInterface
    {
        assert($keywordValidator instanceof KeywordValidatorAdapter);

        return $keywordValidator->validator();
    }
}
