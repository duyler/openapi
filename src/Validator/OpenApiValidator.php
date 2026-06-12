<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Format\FormatRegistry;
use Duyler\OpenApi\Validator\Link\LinkContext;
use Duyler\OpenApi\Validator\Link\LinkResolver;
use Duyler\OpenApi\Validator\Schema\RefResolver;
use Duyler\OpenApi\Validator\Validation\CallbackValidator;
use Duyler\OpenApi\Validator\Validation\RequestValidationHandler;
use Duyler\OpenApi\Validator\Validation\ResponseValidationHandler;
use Duyler\OpenApi\Validator\Validation\SchemaValidatorAdapter;
use Duyler\OpenApi\Validator\Validation\WebhookValidator;
use InvalidArgumentException;
use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

use function sprintf;

final readonly class OpenApiValidator implements OpenApiValidatorInterface
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly ValidatorPool $pool,
        private readonly FormatRegistry $formatRegistry,
        private readonly ErrorFormatterInterface $errorFormatter,
        private readonly PathFinder $pathFinder,
        private readonly LoggerInterface $logger,
        private readonly RefResolver $refResolver,
        private readonly RequestValidationHandler $requestValidation,
        private readonly ResponseValidationHandler $responseValidation,
        private readonly SchemaValidatorAdapter $schemaValidation,
        private readonly WebhookValidator $webhookValidation,
        private readonly CallbackValidator $callbackValidation,
        private readonly LinkResolver $linkResolver,
        private readonly ?SchemaCache $cache = null,
        private readonly bool $coercion = false,
        private readonly bool $nullableAsType = true,
        private readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        private readonly bool $securityValidation = false,
        private readonly bool $strictFormats = false,
        private readonly bool $reportDeprecated = false,
    ) {}

    public function getDocument(): OpenApiDocument
    {
        return $this->document;
    }

    public function getPool(): ValidatorPool
    {
        return $this->pool;
    }

    public function isCoercion(): bool
    {
        return $this->coercion;
    }

    public function isNullableAsType(): bool
    {
        return $this->nullableAsType;
    }

    public function getEmptyArrayStrategy(): EmptyArrayStrategy
    {
        return $this->emptyArrayStrategy;
    }

    public function getErrorFormatter(): ErrorFormatterInterface
    {
        return $this->errorFormatter;
    }

    public function getCache(): ?SchemaCache
    {
        return $this->cache;
    }

    #[Override]
    public function validateRequest(ServerRequestInterface $request): Operation
    {
        return $this->requestValidation->validate($request);
    }

    #[Override]
    public function validateResponse(ResponseInterface $response, Operation $operation): void
    {
        $this->responseValidation->validate($response, $operation);
    }

    #[Override]
    public function validateSchema(mixed $data, string $schemaRef): void
    {
        $this->schemaValidation->validate($data, $schemaRef);
    }

    #[Override]
    public function getFormattedErrors(ValidationException $e): string
    {
        return $this->errorFormatter->formatMultiple($e->getErrors());
    }

    #[Override]
    public function reset(): void
    {
        $this->pool->clear();
        $this->refResolver->clear();
    }

    #[Override]
    public function validateWebhook(ServerRequestInterface $request, string $webhookName): Operation
    {
        return $this->webhookValidation->validate($request, $webhookName);
    }

    #[Override]
    public function validateCallback(ServerRequestInterface $request, string $callbackName): Operation
    {
        return $this->callbackValidation->validate($request, $callbackName);
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
}
