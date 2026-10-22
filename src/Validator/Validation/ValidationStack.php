<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\ParameterValidationConfig;
use Duyler\OpenApi\Validator\Dto\SchemaValidatorDependencies;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\HeadersValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\QueryParametersValidator;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\QueryStringValidator;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use Duyler\OpenApi\Validator\Schema\SchemaValidatorWithContext;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorDispatcher;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\Validation\Internal\ValidationGroup;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ValidationStack
{
    public readonly RequestValidator $requestValidator;
    public readonly ResponseValidatorWithContext $responseValidator;
    public readonly SchemaValidatorWithContext $schemaValidatorWithContext;
    private readonly StatelessValidatorRegistry $statelessValidators;
    private readonly SchemaValidatorDependencies $schemaValidatorDependencies;
    private readonly ValidatorConfiguration $validatorConfiguration;

    /**
     * @param int $maxJsonBodyBytes
     * @param int $maxMultipartBodyBytes
     * @param int $maxRegexBacktracks
     * @param int $maxStreamingRecords
     */
    private function __construct(
        public readonly OpenApiDocument $document,
        public readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        public readonly ErrorFormatterInterface $errorFormatter,
        public readonly RefResolver $refResolver,
        public readonly bool $coercion = false,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly bool $reportDeprecated = false,
        public readonly LoggerInterface $logger = new NullLogger(),
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        public readonly bool $strictFormats = false,
        public readonly PathRegexCache $pathRegexCache = new PathRegexCache(),
        public readonly RegexValidator $regexValidator = new RegexValidator(),
        public readonly int $maxJsonBodyBytes = ValidatorConfiguration::DEFAULT_MAX_JSON_BODY_BYTES,
        public readonly int $maxMultipartBodyBytes = ValidatorConfiguration::DEFAULT_MAX_MULTIPART_BODY_BYTES,
        public readonly bool $strictStreaming = false,
        public readonly int $maxRegexBacktracks = PregExecutor::DEFAULT_MAX_BACKTRACKS,
        public readonly int $maxStreamingRecords = ValidatorConfiguration::DEFAULT_MAX_STREAMING_RECORDS,
        public readonly PregExecutor $pregExecutor = new PregExecutor(),
        public readonly ?LoggerInterface $securityVerboseLogger = null,
        public readonly bool $strictCoercion = true,
    ) {
        $this->validatorConfiguration = new ValidatorConfiguration(
            coercion: $this->coercion,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            reportDeprecated: $this->reportDeprecated,
            strictFormats: $this->strictFormats,
            maxJsonBodyBytes: $this->maxJsonBodyBytes,
            maxMultipartBodyBytes: $this->maxMultipartBodyBytes,
            strictStreaming: $this->strictStreaming,
            maxRegexBacktracks: $this->maxRegexBacktracks,
            maxStreamingRecords: $this->maxStreamingRecords,
            strictCoercion: $this->strictCoercion,
        );

        $this->statelessValidators = new StatelessValidatorRegistry(
            $this->pool,
            $this->formatRegistry,
            $this->strictFormats,
            $this->reportDeprecated,
            $this->logger,
            $this->eventDispatcher,
            $this->document,
            $this->refResolver,
        );

        $this->schemaValidatorDependencies = new SchemaValidatorDependencies(
            pool: $this->pool,
            refResolver: $this->refResolver,
            statelessValidators: $this->statelessValidators,
            logger: $this->logger,
            eventDispatcher: $this->eventDispatcher,
            errorFormatter: $this->errorFormatter,
            formatRegistry: $this->formatRegistry,
            bodyParser: new BodyParser(
                jsonParser: new JsonBodyParser(),
                formParser: new FormBodyParser(new QueryParser()),
                multipartParser: new MultipartBodyParser(),
                textParser: new TextBodyParser(),
                xmlParser: new XmlBodyParser(logger: $this->logger),
            ),
            strictFormats: $this->strictFormats,
            reportDeprecated: $this->reportDeprecated,
            regexValidator: $this->regexValidator,
            pregExecutor: $this->pregExecutor,
            document: $this->document,
        );

        $this->schemaValidatorWithContext = $this->schemaValidatorDependencies->rootSchemaValidator(
            $this->document,
            $this->validatorConfiguration,
        );
        $this->requestValidator = $this->buildRequestValidator();
        $this->responseValidator = new ResponseValidatorWithContext(
            document: $this->document,
            dependencies: $this->schemaValidatorDependencies,
            configuration: $this->validatorConfiguration,
        );
    }

    public static function fromGroup(ValidationGroup $group): self
    {
        $root = $group->root;
        $options = $group->options;
        $limits = $group->bodyLimits;

        return new self(
            document: $root->document,
            pool: $root->pool,
            formatRegistry: $root->formatRegistry,
            errorFormatter: $root->errorFormatter,
            refResolver: $root->refResolver,
            coercion: $options->coercion,
            nullableAsType: $options->nullableAsType,
            emptyArrayStrategy: $options->emptyArrayStrategy,
            reportDeprecated: $options->reportDeprecated,
            logger: $options->logger,
            eventDispatcher: $options->eventDispatcher,
            strictFormats: $options->strictFormats,
            pathRegexCache: $root->pathRegexCache,
            regexValidator: $root->regexValidator,
            pregExecutor: $root->pregExecutor,
            maxJsonBodyBytes: $limits->maxJsonBodyBytes,
            maxMultipartBodyBytes: $limits->maxMultipartBodyBytes,
            strictStreaming: $options->strictStreaming,
            maxRegexBacktracks: $limits->maxRegexBacktracks,
            maxStreamingRecords: $options->maxStreamingRecords,
            securityVerboseLogger: $options->securityVerboseLogger,
            strictCoercion: $options->strictCoercion,
        );
    }

    private function buildRequestValidator(): RequestValidator
    {
        $queryParser = new QueryParser();
        $schemaValidator = $this->buildStatelessSchemaValidator();
        $parameterConfig = $this->buildParameterValidationConfig();
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();

        return new RequestValidator(
            pathParser: new PathParser($this->pathRegexCache, $this->pregExecutor, $this->logger),
            pathParamsValidator: new PathParametersValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                pool: $this->pool,
                coercion: $this->coercion,
                config: $parameterConfig,
            ),
            queryParser: $queryParser,
            queryParamsValidator: new QueryParametersValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                pool: $this->pool,
                coercion: $this->coercion,
                config: $parameterConfig,
            ),
            queryStringValidator: new QueryStringValidator(
                queryParser: $queryParser,
                schemaValidator: $schemaValidator,
                pool: $this->pool,
                config: $parameterConfig,
            ),
            headersValidator: new HeadersValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                pool: $this->pool,
                coercion: $this->coercion,
                config: $parameterConfig,
            ),
            cookieValidator: new CookieValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                pool: $this->pool,
                coercion: $this->coercion,
                config: $parameterConfig,
            ),
            bodyValidator: $this->buildRequestBodyValidator(),
        );
    }

    private function buildStatelessSchemaValidator(): StatelessValidatorDispatcher
    {
        return new StatelessValidatorDispatcher(
            validators: $this->schemaValidatorDependencies->createFullList(),
            pool: $this->pool,
        );
    }

    private function buildParameterValidationConfig(): ParameterValidationConfig
    {
        return new ParameterValidationConfig(
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            strictCoercion: $this->strictCoercion,
        );
    }

    private function buildRequestBodyValidator(): RequestBodyValidatorWithContext
    {
        return new RequestBodyValidatorWithContext(
            document: $this->document,
            dependencies: $this->schemaValidatorDependencies,
            configuration: $this->validatorConfiguration,
        );
    }
}
