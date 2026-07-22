<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Callback;

use Duyler\OpenApi\Schema\Model\Callbacks;
use Duyler\OpenApi\Schema\Model\Operation;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Callback\Exception\UnknownCallbackException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;
use Duyler\OpenApi\Validator\Exception\UnresolvableCallbackPathException;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Request\PathRegexCache;
use Duyler\OpenApi\Validator\Request\RequestValidatorInterface;
use Psr\Http\Message\ServerRequestInterface;

use function array_key_exists;
use function assert;
use function is_string;
use function parse_url;
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

    /**
     * @param bool $strictCallbackRuntimeTemplate Defaults to true (fail-closed):
     *     callback expressions containing runtime templates
     *     (e.g. `{$request.body#/callback_url}`) that cannot be resolved by
     *     the validator throw {@see UnresolvableCallbackPathException}
     *     instead of being treated as wildcards that accept any URL. This
     *     matches the `OpenApiValidatorBuilder` default and prevents SSRF
     *     via attacker-controlled runtime templates when the resolved URL
     *     is used for an outbound HTTP request. Pass false explicitly only
     *     when callback URLs are validated at the application level (see
     *     README section "Callbacks" for the security implications).
     */
    public function __construct(
        private readonly RequestValidatorInterface $requestValidator,
        private readonly PathRegexCache $pathRegexCache,
        private readonly bool $strictCallbackRuntimeTemplate = true,
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    public function validate(
        ServerRequestInterface $request,
        string $callbackName,
        OpenApiDocument $document,
    ): Operation {
        $pathItems = $this->findCallbacks($callbackName, $document);

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

        return $this->getCallbackExpressions($componentCallbacks[$callbackName], $callbackName);
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
        return $callbacks->callbacks[$callbackName] ?? [];
    }

    /**
     * @param array<string, PathItem> $pathItems
     *
     * @return array{0: Operation, 1: string}
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
     * @return string|null
     */
    private function resolvePathTemplate(string $expression, string $requestPath): ?string
    {
        if (str_contains($expression, '{$')) {
            if ($this->strictCallbackRuntimeTemplate) {
                throw new UnresolvableCallbackPathException($expression);
            }

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

            try {
                $matched = 1 === $this->pregExecutor->match($regex, $requestPath);
            } catch (PregRuntimeException) {
                $matched = false;
            }

            return $matched ? $expression : null;
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
