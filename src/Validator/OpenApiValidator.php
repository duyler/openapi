<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\OpenApiValidatorInterface;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Dto\ValidatorConfiguration;
use Duyler\OpenApi\Validator\Dto\ValidatorDependencies;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\Link\LinkContext;
use Duyler\OpenApi\Validator\Link\ResolvedLink;
use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;

final readonly class OpenApiValidator implements OpenApiValidatorInterface
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly ValidatorConfiguration $configuration,
        private readonly ValidatorDependencies $dependencies,
    ) {}

    public function getDocument(): OpenApiDocument
    {
        return $this->document;
    }

    public function getPool(): ValidatorPool
    {
        return $this->dependencies->pool;
    }

    public function isCoercion(): bool
    {
        return $this->configuration->coercion;
    }

    public function isNullableAsType(): bool
    {
        return $this->configuration->nullableAsType;
    }

    public function getEmptyArrayStrategy(): EmptyArrayStrategy
    {
        return $this->configuration->emptyArrayStrategy;
    }

    public function getErrorFormatter(): ErrorFormatterInterface
    {
        return $this->dependencies->errorFormatter;
    }

    public function getCache(): ?SchemaCache
    {
        return $this->dependencies->cache;
    }

    #[Override]
    public function validateRequest(ServerRequestInterface $request): Operation
    {
        return $this->dependencies->requestValidation->validate($request);
    }

    #[Override]
    public function validateResponse(ResponseInterface $response, Operation $operation): void
    {
        $this->dependencies->responseValidation->validate($response, $operation);
    }

    #[Override]
    public function validateSchema(mixed $data, string $schemaRef): void
    {
        $this->dependencies->schemaValidation->validate($data, $schemaRef);
    }

    #[Override]
    public function getFormattedErrors(ValidationException $e): string
    {
        return $this->dependencies->errorFormatter->formatMultiple($e->getErrors());
    }

    #[Override]
    public function reset(): void
    {
        $this->dependencies->pool->clear();
        $this->dependencies->refResolver->clear();
        $this->dependencies->pathRegexCache->clear();
        $this->dependencies->regexValidator->clear();
    }

    #[Override]
    public function validateWebhook(ServerRequestInterface $request, string $webhookName): Operation
    {
        return $this->dependencies->webhookValidation->validate($request, $webhookName);
    }

    #[Override]
    public function validateCallback(ServerRequestInterface $request, string $callbackName): Operation
    {
        return $this->dependencies->callbackValidation->validate($request, $callbackName);
    }

    /**
     * Resolve link parameters from response data.
     *
     * Limitation: this method only resolves component-level links
     * (defined under components/links). Operation-level links defined
     * inline within a response's links property are not supported.
     *
     * @param array<string, mixed> $responseData Response body data to extract values from
     */
    #[Override]
    public function resolveLink(string $linkName, array $responseData): ResolvedLink
    {
        $context = new LinkContext(body: $responseData);

        return $this->resolveLinkWithContext($linkName, $context);
    }

    /**
     * Resolves link parameters using full Runtime Expression context.
     *
     * Limitation: only component-level links (defined under
     * components/links) are resolved. Operation-level links defined
     * inline within a response's links property are not supported.
     */
    #[Override]
    public function resolveLinkWithContext(string $linkName, LinkContext $context): ResolvedLink
    {
        $links = $this->document->components?->links ?? [];

        $link = $links[$linkName] ?? null;

        if (null === $link) {
            throw new InvalidArgumentException(
                sprintf('Unknown link: %s', $linkName),
            );
        }

        return $this->dependencies->linkResolver->resolve($link, $context);
    }
}
