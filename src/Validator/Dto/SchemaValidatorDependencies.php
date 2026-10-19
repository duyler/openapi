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
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidatorInterface;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorFactory;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WeakMap;

use function array_values;

/**
 * @psalm-type StatelessValidatorList = list<SchemaValidatorInterface>
 */
final class SchemaValidatorDependencies
{
    public readonly LoggerInterface $logger;
    public readonly ErrorFormatterInterface $errorFormatter;
    public readonly FormatRegistry $formatRegistry;
    public readonly BodyParser $bodyParser;

    /** @var WeakMap<OpenApiDocument, SchemaValidatorWithContext> */
    private WeakMap $rootSchemaValidators;

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
        return new ValidatorFactory($this->createDependencies())->createStatelessList();
    }

    /**
     * @return list<SchemaValidatorInterface>
     */
    public function createFullList(): array
    {
        return array_values(new ValidatorFactory($this->createDependencies())->createAll());
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

    private function createDependencies(): ValidatorDependencies
    {
        return new ValidatorDependencies(
            pool: $this->pool,
            formatRegistry: $this->formatRegistry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
            reportDeprecated: $this->reportDeprecated,
            eventDispatcher: $this->eventDispatcher,
            regexValidator: $this->regexValidator,
            pregExecutor: $this->pregExecutor,
            document: $this->document,
            refResolver: $this->refResolver,
        );
    }
}
