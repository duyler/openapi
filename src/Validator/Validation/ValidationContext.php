<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Validation;

use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Request\BodyParser\BodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\FormBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\JsonBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\MultipartBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\TextBodyParser;
use Duyler\OpenApi\Validator\Request\BodyParser\XmlBodyParser;
use Duyler\OpenApi\Validator\Request\ContentTypeNegotiator;
use Duyler\OpenApi\Validator\Request\CookieValidator;
use Duyler\OpenApi\Validator\Request\HeadersValidator;
use Duyler\OpenApi\Validator\Request\ParameterDeserializer;
use Duyler\OpenApi\Validator\Request\PathParametersValidator;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\QueryParametersValidator;
use Duyler\OpenApi\Validator\Request\QueryParser;
use Duyler\OpenApi\Validator\Request\QueryStringValidator;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use Duyler\OpenApi\Validator\Response\StatusCodeValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class ValidationContext
{
    public readonly RequestValidator $requestValidator;
    public readonly ResponseValidatorWithContext $responseValidator;
    private readonly StatelessValidatorRegistry $statelessValidators;

    public function __construct(
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
    ) {
        $this->statelessValidators = new StatelessValidatorRegistry(
            $this->pool,
            $this->formatRegistry,
            $this->reportDeprecated,
            $this->logger,
            $this->eventDispatcher,
        );
        $this->requestValidator = $this->buildRequestValidator();
        $this->responseValidator = $this->buildResponseValidator();
    }

    private function buildRequestValidator(): RequestValidator
    {
        $deserializer = new ParameterDeserializer();
        $coercer = new TypeCoercer();
        $bodyParser = new BodyParser(
            jsonParser: new JsonBodyParser(),
            formParser: new FormBodyParser(),
            multipartParser: new MultipartBodyParser(),
            textParser: new TextBodyParser(),
            xmlParser: new XmlBodyParser(),
        );

        $queryParser = new QueryParser();
        $schemaValidator = new SchemaValidator(
            $this->pool,
            $this->formatRegistry,
            strictFormats: $this->strictFormats,
            logger: $this->logger,
            reportDeprecated: $this->reportDeprecated,
            eventDispatcher: $this->eventDispatcher,
        );

        return new RequestValidator(
            pathParser: new PathParser(),
            pathParamsValidator: new PathParametersValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                coercion: $this->coercion,
            ),
            queryParser: $queryParser,
            queryParamsValidator: new QueryParametersValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                coercion: $this->coercion,
            ),
            queryStringValidator: new QueryStringValidator(
                queryParser: $queryParser,
                schemaValidator: $schemaValidator,
            ),
            headersValidator: new HeadersValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                coercion: $this->coercion,
            ),
            cookieValidator: new CookieValidator(
                schemaValidator: $schemaValidator,
                deserializer: $deserializer,
                coercer: $coercer,
                coercion: $this->coercion,
            ),
            bodyValidator: new RequestBodyValidatorWithContext(
                pool: $this->pool,
                document: $this->document,
                bodyParser: $bodyParser,
                statelessValidators: $this->statelessValidators,
                refResolver: $this->refResolver,
                formatRegistry: $this->formatRegistry,
                negotiator: new ContentTypeNegotiator(),
                nullableAsType: $this->nullableAsType,
                emptyArrayStrategy: $this->emptyArrayStrategy,
                coercion: $this->coercion,
                reportDeprecated: $this->reportDeprecated,
                logger: $this->logger,
                eventDispatcher: $this->eventDispatcher,
                errorFormatter: $this->errorFormatter,
            ),
        );
    }

    private function buildResponseValidator(): ResponseValidatorWithContext
    {
        return new ResponseValidatorWithContext(
            pool: $this->pool,
            document: $this->document,
            statelessValidators: $this->statelessValidators,
            formatRegistry: $this->formatRegistry,
            coercion: $this->coercion,
            statusCodeValidator: new StatusCodeValidator(),
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            refResolver: $this->refResolver,
            reportDeprecated: $this->reportDeprecated,
            logger: $this->logger,
            eventDispatcher: $this->eventDispatcher,
            errorFormatter: $this->errorFormatter,
        );
    }
}
