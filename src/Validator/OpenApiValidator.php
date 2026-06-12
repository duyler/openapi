<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Event\ValidationErrorEvent;
use Duyler\OpenApi\Event\ValidationFinishedEvent;
use Duyler\OpenApi\Event\ValidationStartedEvent;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\Model\Operation as OperationModel;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Callback\CallbackValidator;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Cache\SchemaCache;
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
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Request\RequestBodyValidatorWithContext;
use Duyler\OpenApi\Validator\Request\TypeCoercer;
use Duyler\OpenApi\Validator\Response\ResponseValidatorWithContext;
use Duyler\OpenApi\Validator\Response\StatusCodeValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Schema\StatelessValidatorRegistry;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\Security\SecurityValidator;
use Duyler\OpenApi\Validator\Link\LinkContext;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\Webhook\WebhookValidator;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use InvalidArgumentException;

use function sprintf;

final readonly class OpenApiValidator implements OpenApiValidatorInterface
{
    use EventDispatchingTrait;

    private readonly RequestValidator $requestValidator;
    private readonly ResponseValidatorWithContext $responseValidator;
    private readonly RefResolver $refResolver;
    private readonly WebhookValidator $webhookValidator;
    private readonly CallbackValidator $callbackValidator;
    private readonly LinkResolver $linkResolver;
    private readonly SecurityValidator $securityValidator;
    private readonly LoggerInterface $logger;
    private readonly StatelessValidatorRegistry $statelessValidators;
    private readonly SchemaValidator $schemaValidator;

    public function __construct(
        public readonly OpenApiDocument $document,
        public readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        public readonly ErrorFormatterInterface $errorFormatter,
        private readonly PathFinder $pathFinder,
        public readonly ?SchemaCache $cache = null,
        ?LoggerInterface $logger = null,
        public readonly bool $coercion = false,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
        public readonly bool $securityValidation = false,
        public readonly bool $strictFormats = false,
        public readonly bool $reportDeprecated = false,
    ) {
        $this->logger = $logger ?? new NullLogger();
        $this->statelessValidators = new StatelessValidatorRegistry($this->pool, $this->formatRegistry, $this->reportDeprecated, $this->logger, $this->eventDispatcher);
        $this->refResolver = new RefResolver();
        $this->schemaValidator = new SchemaValidator($this->pool, $this->formatRegistry, strictFormats: $this->strictFormats, logger: $this->logger, reportDeprecated: $this->reportDeprecated, eventDispatcher: $this->eventDispatcher);
        $this->requestValidator = $this->buildRequestValidator();
        $this->responseValidator = $this->buildResponseValidator();
        $this->webhookValidator = new WebhookValidator($this->requestValidator);
        $this->callbackValidator = new CallbackValidator($this->requestValidator);
        $this->linkResolver = new LinkResolver();
        $this->securityValidator = new SecurityValidator();
    }

    #[Override]
    public function validateRequest(
        ServerRequestInterface $request,
    ): Operation {
        $requestPath = $request->getUri()->getPath();
        $method = $request->getMethod();

        return $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(request: $request, path: $requestPath, method: $method),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                request: $request,
                path: $requestPath,
                method: $method,
                success: $success,
                duration: $duration,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                request: $request,
                path: $requestPath,
                method: $method,
                exception: $e,
            ),
            callback: function () use ($request, $requestPath, $method): Operation {
                $operation = $this->pathFinder->findOperation($requestPath, $method);

                $pathItem = $this->document->paths?->paths[$operation->path] ?? null;
                if (null === $pathItem) {
                    throw new BuilderException(sprintf('Path not found: %s', $operation->path));
                }

                $op = $this->getOperationFromPathItem($pathItem, $method);
                if (null === $op) {
                    throw new BuilderException(
                        sprintf('Method not found: %s %s', $method, $operation->path),
                    );
                }

                $this->logger->info(sprintf('Validating request: %s %s', $method, $requestPath));

                $this->requestValidator->validate($request, $op, $operation->path);

                if ($this->securityValidation) {
                    $securityRequirements = $op->security ?? $this->document->security;

                    if (null !== $securityRequirements) {
                        $securitySchemes = $this->document->components?->securitySchemes ?? [];
                        $this->securityValidator->validate(
                            $request,
                            $operation->path,
                            $operation->method,
                            $securityRequirements,
                            $securitySchemes,
                        );
                    }
                }

                return $operation;
            },
            warningMessage: sprintf('Request validation failed: %s %s', $method, $requestPath),
        );
    }

    #[Override]
    public function validateResponse(
        ResponseInterface $response,
        Operation $operation,
    ): void {
        $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(path: $operation->path, method: $operation->method, response: $response),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                path: $operation->path,
                method: $operation->method,
                success: $success,
                duration: $duration,
                response: $response,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                path: $operation->path,
                method: $operation->method,
                exception: $e,
                response: $response,
            ),
            callback: function () use ($response, $operation): void {
                $pathItem = $this->document->paths?->paths[$operation->path] ?? null;
                if (null === $pathItem) {
                    throw new BuilderException(sprintf('Path not found: %s', $operation->path));
                }

                $op = $this->getOperationFromPathItem($pathItem, $operation->method);
                if (null === $op) {
                    throw new BuilderException(
                        sprintf('Method not found: %s %s', $operation->method, $operation->path),
                    );
                }

                $this->logger->info(sprintf('Validating response: %s %s', $operation->method, $operation->path));

                $this->responseValidator->validate($response, $op);
            },
            warningMessage: sprintf('Response validation failed: %s %s', $operation->method, $operation->path),
        );
    }

    #[Override]
    public function validateSchema(mixed $data, string $schemaRef): void
    {
        $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(path: $schemaRef, method: 'SCHEMA', schemaRef: $schemaRef),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                path: $schemaRef,
                method: 'SCHEMA',
                success: $success,
                duration: $duration,
                schemaRef: $schemaRef,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                path: $schemaRef,
                method: 'SCHEMA',
                exception: $e,
                schemaRef: $schemaRef,
            ),
            callback: function () use ($data, $schemaRef): void {
                $schema = $this->resolveSchema($schemaRef);

                $this->logger->info(sprintf('Validating schema: %s', $schemaRef));

                /** @var array<array-key, mixed>|array-key $data */
                $this->schemaValidator->validate($data, $schema);
            },
            warningMessage: sprintf('Schema validation failed: %s', $schemaRef),
        );
    }

    #[Override]
    public function getFormattedErrors(ValidationException $e): string
    {
        return $this->errorFormatter->formatMultiple($e->getErrors());
    }

    public function reset(): void
    {
        $this->pool->clear();
        $this->refResolver->clear();
    }

    #[Override]
    public function validateWebhook(
        ServerRequestInterface $request,
        string $webhookName,
    ): Operation {
        $method = $request->getMethod();

        return $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(request: $request, path: $webhookName, method: $method),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                request: $request,
                path: $webhookName,
                method: $method,
                success: $success,
                duration: $duration,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                request: $request,
                path: $webhookName,
                method: $method,
                exception: $e,
            ),
            callback: function () use ($request, $webhookName, $method): Operation {
                $this->webhookValidator->validate($request, $webhookName, $this->document);

                return new Operation($webhookName, $method);
            },
        );
    }

    #[Override]
    public function validateCallback(
        ServerRequestInterface $request,
        string $callbackName,
    ): Operation {
        $method = $request->getMethod();

        return $this->withValidationEvents(
            startedEvent: new ValidationStartedEvent(request: $request, path: $callbackName, method: $method),
            makeFinishedEvent: fn(bool $success, float $duration): ValidationFinishedEvent => new ValidationFinishedEvent(
                request: $request,
                path: $callbackName,
                method: $method,
                success: $success,
                duration: $duration,
            ),
            makeErrorEvent: fn(ValidationException $e): ValidationErrorEvent => new ValidationErrorEvent(
                request: $request,
                path: $callbackName,
                method: $method,
                exception: $e,
            ),
            callback: function () use ($request, $callbackName, $method): Operation {
                $this->callbackValidator->validate($request, $callbackName, $this->document);

                return new Operation($callbackName, $method);
            },
        );
    }

    #[Override]
    public function resolveLink(string $linkName, array $responseData): array
    {
        $context = new LinkContext(body: $responseData);

        return $this->resolveLinkWithContext($linkName, $context);
    }

    #[Override]
    public function resolveLinkWithContext(string $linkName, LinkContext $context): array
    {
        $links = $this->document->components?->links ?? [];

        $link = $links[$linkName] ?? null;

        if (null === $link) {
            throw new InvalidArgumentException(
                sprintf('Unknown link: %s', $linkName),
            );
        }

        return $this->linkResolver->resolve($link, $context);
    }

    private function dispatchValidationEvent(object $event): void
    {
        $this->eventDispatcher?->dispatch($event);
    }

    private function getOperationFromPathItem(PathItem $pathItem, string $method): ?OperationModel
    {
        $method = strtolower($method);

        $standardOperation = match ($method) {
            'get' => $pathItem->get,
            'post' => $pathItem->post,
            'put' => $pathItem->put,
            'patch' => $pathItem->patch,
            'delete' => $pathItem->delete,
            'options' => $pathItem->options,
            'head' => $pathItem->head,
            'trace' => $pathItem->trace,
            'query' => $pathItem->query,
            default => null,
        };

        if (null !== $standardOperation) {
            return $standardOperation;
        }

        if (null !== $pathItem->additionalOperations) {
            foreach ($pathItem->additionalOperations as $opMethod => $operation) {
                if (strtolower($opMethod) === $method) {
                    return $operation;
                }
            }
        }

        return null;
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
        $schemaValidator = new SchemaValidator($this->pool, $this->formatRegistry, strictFormats: $this->strictFormats, logger: $this->logger, reportDeprecated: $this->reportDeprecated, eventDispatcher: $this->eventDispatcher);

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

    private function resolveSchema(string $ref): Schema
    {
        $this->logger->info(sprintf('Resolving schema ref: %s', $ref));

        return $this->refResolver->resolve($ref, $this->document);
    }
}
