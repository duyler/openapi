<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function assert;
use function is_array;
use function is_string;
use function parse_url;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;

use const PHP_URL_PATH;

final readonly class CallbackValidator
{
    private const string PATH_ITEM_REF_PREFIX = '#/components/pathItems/';

    /** @var list<string> */
    private const array HTTP_METHODS = [
        'get',
        'put',
        'post',
        'delete',
        'options',
        'head',
        'patch',
        'trace',
        'query',
    ];

    public function __construct(
        private readonly RequestValidatorInterface $requestValidator,
        private readonly PathRegexCache $pathRegexCache,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        string $callbackName,
        OpenApiDocument $document,
    ): Operation {
        $pathItems = $this->findCallbacks($callbackName, $document);

        /**
         * @var array{0: Operation, 1: string} $matched
         */
        $matched = $this->extractOperation($request, $callbackName, $pathItems, $document);
        $operation = $matched[0];
        $pathTemplate = $matched[1];

        $this->requestValidator->validate($request, $operation, $pathTemplate);

        return $operation;
    }

    /**
     * @return array<string, PathItem>
     */
    private function findCallbacks(string $callbackName, OpenApiDocument $document): array
    {
        $componentPathItems = $this->findComponentCallbacks($callbackName, $document);

        if ([] !== $componentPathItems) {
            return $componentPathItems;
        }

        $inlinePathItems = $this->findInlineCallbacks($callbackName, $document);

        if ([] !== $inlinePathItems) {
            return $inlinePathItems;
        }

        throw new UnknownCallbackException($callbackName);
    }

    /**
     * @return array<string, PathItem>
     */
    private function findComponentCallbacks(string $callbackName, OpenApiDocument $document): array
    {
        $componentCallbacks = $document->components?->callbacks ?? [];

        if (false === array_key_exists($callbackName, $componentCallbacks)) {
            return [];
        }

        /** @var Callbacks $callback */
        $callback = $componentCallbacks[$callbackName];

        return $this->getCallbackExpressions($callback, $callbackName);
    }

    /**
     * @return array<string, PathItem>
     */
    private function findInlineCallbacks(string $callbackName, OpenApiDocument $document): array
    {
        $paths = $document->paths?->paths ?? [];
        $pathItems = [];

        foreach ($paths as $pathItem) {
            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem->getOperation($method);

                if (null === $operation || null === $operation->callbacks) {
                    continue;
                }

                foreach ($this->getCallbackExpressions($operation->callbacks, $callbackName) as $expression => $item) {
                    $pathItems[$expression] = $item;
                }
            }
        }

        return $pathItems;
    }

    /**
     * @return array<string, PathItem>
     */
    private function getCallbackExpressions(Callbacks $callbacks, string $callbackName): array
    {
        $expressions = $callbacks->callbacks[$callbackName] ?? null;

        if (false === is_array($expressions)) {
            return [];
        }

        return $expressions;
    }

    /**
     * Resolve the request against declared callback expressions.
     *
     * @param array<string, PathItem> $pathItems
     *
     * @return array{0: Operation, 1: string} Matched operation and the path template to validate against
     */
    private function extractOperation(
        ServerRequestInterface $request,
        string $callbackName,
        array $pathItems,
        OpenApiDocument $document,
    ): array {
        $method = strtolower($request->getMethod());
        $requestPath = $request->getUri()->getPath();

        foreach ($pathItems as $expression => $pathItem) {
            $pathTemplate = $this->resolvePathTemplate($expression, $requestPath);

            if (null === $pathTemplate) {
                continue;
            }

            $resolved = $this->resolvePathItemRef($pathItem, $document);

            $operation = $resolved->getOperation($method);

            if (null !== $operation) {
                return [$operation, $pathTemplate];
            }
        }

        throw new UnknownCallbackException(
            sprintf('%s (method: %s)', $callbackName, $method),
        );
    }

    /**
     * Resolve a callback expression into the path template that should be used
     * to validate the incoming request.
     *
     * Runtime expressions (containing "{$" markers) reference the original
     * triggering request and cannot be resolved without its body, so they
     * are treated as wildcards that accept any URL. The request path itself
     * is returned as the template.
     *
     * Full URL expressions (e.g. "https://example.com/webhook") are parsed
     * and their path component is compared against the request path. When
     * matched, the parsed path is returned as the template.
     *
     * Path template expressions (e.g. "/callbacks/{eventId}") follow the
     * OpenAPI 3.2 specification: every "{paramName}" placeholder matches a
     * single path segment. The expression itself is returned as the template
     * so that path parameters can be extracted and validated downstream.
     *
     * Fixed path expressions are matched by exact comparison against the
     * request path and returned as-is.
     *
     * @return string|null Path template to use for request validation, or null when the expression does not match the request URL
     */
    private function resolvePathTemplate(string $expression, string $requestPath): ?string
    {
        if (str_contains($expression, '{$')) {
            return $requestPath;
        }

        if (str_starts_with($expression, 'http://') || str_starts_with($expression, 'https://')) {
            $parsedPath = parse_url($expression, PHP_URL_PATH);

            if (is_string($parsedPath) && '' !== $parsedPath && $parsedPath === $requestPath) {
                return $parsedPath;
            }

            return null;
        }

        if (str_contains($expression, '{') && str_contains($expression, '}')) {
            $regex = $this->pathRegexCache->getOrCompute($expression);

            assert('' !== $regex);

            return 1 === preg_match($regex, $requestPath) ? $expression : null;
        }

        return $expression === $requestPath ? $expression : null;
    }

    private function resolvePathItemRef(PathItem $pathItem, OpenApiDocument $document): PathItem
    {
        if (null === $pathItem->ref) {
            return $pathItem;
        }

        if (false === str_starts_with($pathItem->ref, self::PATH_ITEM_REF_PREFIX)) {
            throw new RefResolutionException(
                sprintf('Unsupported callback $ref "%s": only "%s" is supported.', $pathItem->ref, self::PATH_ITEM_REF_PREFIX),
            );
        }

        $name = substr($pathItem->ref, strlen(self::PATH_ITEM_REF_PREFIX));
        $resolved = $document->components?->pathItems[$name] ?? null;

        if (false === $resolved instanceof PathItem) {
            throw new RefResolutionException(
                sprintf('Callback $ref "%s" not found in components.pathItems.', $pathItem->ref),
            );
        }

        return $resolved;
    }
}
