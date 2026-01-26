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
use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
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
use Duyler\OpenApi\Validator\Request\RequestBodyValidator;
use Duyler\OpenApi\Validator\Request\RequestValidator;
use Duyler\OpenApi\Validator\Response\ResponseBodyValidator;
use Duyler\OpenApi\Validator\Response\ResponseHeadersValidator;
use Duyler\OpenApi\Validator\Response\ResponseValidator;
use Duyler\OpenApi\Validator\Response\StatusCodeValidator;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;

/**
 * OpenAPI validator for HTTP requests and responses.
 *
 * Validates incoming requests and outgoing responses against OpenAPI specification.
 * Supports caching, custom format validators, error formatting, and event dispatching.
 */
readonly class OpenApiValidator implements OpenApiValidatorInterface
{
    public function __construct(
        public readonly OpenApiDocument $document,
        public readonly ValidatorPool $pool,
        public readonly FormatRegistry $formatRegistry,
        public readonly ErrorFormatterInterface $errorFormatter,
        private readonly PathFinder $pathFinder,
        public readonly ?object $cache = null,
        public readonly ?object $logger = null,
        public readonly bool $coercion = false,
        public readonly bool $nullableAsType = false,
        public readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {}

    /**
     * Validate HTTP request against OpenAPI specification and return matched operation.
     *
     * @param ServerRequestInterface $request PSR-7 HTTP request
     * @return Operation Matched operation from OpenAPI specification
     * @throws ValidationException If validation fails
     *
     * @example
     * $operation = $validator->validateRequest($request);
     */
    #[Override]
    public function validateRequest(
        ServerRequestInterface $request,
    ): Operation {
        $startTime = microtime(true);

        $requestPath = $request->getUri()->getPath();
        $method = $request->getMethod();

        if (null !== $this->eventDispatcher) {
            $this->eventDispatcher->dispatch(
                new ValidationStartedEvent($request, $requestPath, $method),
            );
        }

        try {
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

            $requestValidator = $this->createRequestValidator();
            $requestValidator->validate($request, $op, $operation->path);

            if (null !== $this->eventDispatcher) {
                $duration = microtime(true) - $startTime;
                $this->eventDispatcher->dispatch(
                    new ValidationFinishedEvent(
                        $request,
                        $operation->path,
                        $operation->method,
                        true,
                        $duration,
                    ),
                );
            }

            return $operation;
        } catch (BuilderException|ValidationException $e) {
            if (null !== $this->eventDispatcher) {
                $duration = microtime(true) - $startTime;
                $this->eventDispatcher->dispatch(
                    new ValidationFinishedEvent(
                        $request,
                        $requestPath,
                        $method,
                        false,
                        $duration,
                    ),
                );

                if ($e instanceof ValidationException) {
                    $this->eventDispatcher->dispatch(
                        new ValidationErrorEvent($request, $requestPath, $method, $e),
                    );
                }
            }

            throw $e;
        }
    }

    /**
     * Validate HTTP response against OpenAPI specification.
     *
     * @param ResponseInterface $response PSR-7 HTTP response
     * @param Operation $operation Operation to validate against
     * @return void
     * @throws ValidationException If validation fails
     *
     * @example
     * $validator->validateResponse($response, $operation);
     */
    #[Override]
    public function validateResponse(
        ResponseInterface $response,
        Operation $operation,
    ): void {
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

        $responseValidator = $this->createResponseValidator();
        $responseValidator->validate($response, $op);
    }

    #[Override]
    public function validateSchema(mixed $data, string $schemaRef): void
    {
        $schema = $this->resolveSchema($schemaRef);
        $context = $this->createValidationContext();

        $validator = new SchemaValidator($this->pool, $this->formatRegistry);

        /** @var array<array-key, mixed>|array-key $data */
        $validator->validate($data, $schema);
    }

    #[Override]
    public function getFormattedErrors(ValidationException $e): string
    {
        return $this->errorFormatter->formatMultiple($e->getErrors());
    }

    private function getOperationFromPathItem(PathItem $pathItem, string $method): ?OperationModel
    {
        return match (strtolower($method)) {
            'get' => $pathItem->get,
            'post' => $pathItem->post,
            'put' => $pathItem->put,
            'patch' => $pathItem->patch,
            'delete' => $pathItem->delete,
            'options' => $pathItem->options,
            'head' => $pathItem->head,
            'trace' => $pathItem->trace,
            default => null,
        };
    }

    private function createRequestValidator(): RequestValidator
    {
        $deserializer = new ParameterDeserializer();

        return new RequestValidator(
            pathParser: new PathParser(),
            pathParamsValidator: new PathParametersValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
                deserializer: $deserializer,
            ),
            queryParser: new QueryParser(),
            queryParamsValidator: new QueryParametersValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
                deserializer: $deserializer,
            ),
            headersValidator: new HeadersValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
            ),
            cookieValidator: new CookieValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
                deserializer: $deserializer,
            ),
            bodyValidator: new RequestBodyValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
                negotiator: new ContentTypeNegotiator(),
                jsonParser: new JsonBodyParser(),
                formParser: new FormBodyParser(),
                multipartParser: new MultipartBodyParser(),
                textParser: new TextBodyParser(),
                xmlParser: new XmlBodyParser(),
            ),
        );
    }

    private function createResponseValidator(): ResponseValidator
    {
        return new ResponseValidator(
            statusCodeValidator: new StatusCodeValidator(),
            headersValidator: new ResponseHeadersValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
            ),
            bodyValidator: new ResponseBodyValidator(
                schemaValidator: new SchemaValidator($this->pool, $this->formatRegistry),
                negotiator: new ContentTypeNegotiator(),
                jsonParser: new JsonBodyParser(),
                formParser: new FormBodyParser(),
                multipartParser: new MultipartBodyParser(),
                textParser: new TextBodyParser(),
                xmlParser: new XmlBodyParser(),
            ),
        );
    }

    private function createValidationContext(): ValidationContext
    {
        return new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
        );
    }

    /**
     * @throws BuilderException
     */
    private function resolveSchema(string $ref): Schema
    {
        $resolver = new RefResolver();

        return $resolver->resolve($ref, $this->document);
    }
}
