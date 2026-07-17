<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;

use function array_key_exists;
use function array_is_list;
use function array_map;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;
use function strval;
use function trim;

final readonly class LinkResolver
{
    public function resolve(Link $link, LinkContext $context): ResolvedLink
    {
        $parameters = $this->resolveParameters($link->parameters, $context);

        return new ResolvedLink(
            parameters: $parameters,
            requestBody: $this->resolveValue($link->requestBody, $context),
            server: $link->server,
        );
    }

    /**
     * @param array<string, mixed>|null $parameters
     *
     * @return array<string, mixed>
     */
    private function resolveParameters(?array $parameters, LinkContext $context): array
    {
        if (null === $parameters) {
            return [];
        }

        /** @var array<string, mixed> $resolved */
        $resolved = [];

        foreach ($parameters as $name => $expression) {
            $resolved[$name] = $this->resolveValue($expression, $context);
        }

        return $resolved;
    }

    private function resolveValue(mixed $expression, LinkContext $context): mixed
    {
        if (false === is_string($expression)) {
            return $expression;
        }

        return match (true) {
            '$url' === $expression => $context->url,
            '$method' === $expression => $context->method,
            '$statusCode' === $expression => $context->statusCode,
            default => $this->resolveRuntimeExpression($expression, $context),
        };
    }

    /**
     * Resolves OpenAPI 3.2 §6.19.2 runtime expressions of two syntactic forms:
     *  - Named form:   `$<scope>.<path|query|header>.<name>`
     *  - Pointer form: `$<scope>.<body|header|query>[#/pointer]`
     *
     * Returns the literal expression unchanged when neither form matches, so
     * unsupported expressions stay distinguishable from values that
     * legitimately resolve to null.
     */
    private function resolveRuntimeExpression(string $expression, LinkContext $context): mixed
    {
        if (1 === preg_match(
            '/^\$(?<scope>request|response)\.(?<source>path|query|header)\.(?<name>[a-zA-Z0-9_.\-]+)$/',
            $expression,
            $matches,
        )) {
            return $this->resolveNamedExpression(
                $matches['scope'],
                $matches['source'],
                $matches['name'],
                $context,
            );
        }

        if (1 === preg_match(
            '/^\$(?<scope>request|response)\.(?<source>body|header|query)(?:#(?<path>\/.+))?$/',
            $expression,
            $matches,
        )) {
            return $this->resolvePointerExpression(
                $matches['scope'],
                $matches['source'],
                $matches['path'] ?? null,
                $context,
            );
        }

        return $expression;
    }

    private function resolveNamedExpression(
        string $scope,
        string $source,
        string $name,
        LinkContext $context,
    ): mixed {
        return match (true) {
            'request' === $scope && 'path' === $source => $context->pathParams[$name] ?? null,
            'request' === $scope && 'query' === $source => $context->queryParams[$name] ?? null,
            'request' === $scope && 'header' === $source => $this->lookupHeader($context->requestHeaders, $name),
            'response' === $scope && 'query' === $source => $context->queryParams[$name] ?? null,
            'response' === $scope && 'header' === $source => $this->lookupHeader($context->headers, $name),
            default => null,
        };
    }

    private function resolvePointerExpression(
        string $scope,
        string $source,
        ?string $path,
        LinkContext $context,
    ): mixed {
        return $this->extractByPath(
            $this->resolvePointerData($scope, $source, $context),
            $path,
        );
    }

    private function resolvePointerData(string $scope, string $source, LinkContext $context): mixed
    {
        return match (true) {
            'request' === $scope && 'body' === $source => $context->requestBody,
            'request' === $scope && 'header' === $source => $context->requestHeaders,
            'request' === $scope && 'query' === $source => $context->queryParams,
            'response' === $scope && 'body' === $source => $context->body,
            'response' === $scope && 'header' === $source => $context->headers,
            'response' === $scope && 'query' === $source => $context->queryParams,
            default => null,
        };
    }

    /**
     * Performs RFC 9110 case-insensitive lookup of a header value by name.
     *
     * @param array<string, string|list<string>> $headers
     */
    private function lookupHeader(array $headers, string $name): ?string
    {
        $needle = strtolower($name);

        foreach ($headers as $key => $value) {
            if (strtolower($key) !== $needle) {
                continue;
            }

            if (is_array($value)) {
                return implode(',', array_map(strval(...), $value));
            }

            return $value;
        }

        return null;
    }

    private function extractByPath(mixed $data, ?string $path): mixed
    {
        if (null === $path || '' === $path) {
            return $data;
        }

        if (false === is_array($data)) {
            return null;
        }

        /** @var list<string> $segments */
        $segments = explode('/', trim($path, '/'));

        $segments = array_map(
            static fn(string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
            $segments,
        );

        /** @var mixed $current */
        $current = $data;

        foreach ($segments as $segment) {
            if (
                is_array($current)
                && array_is_list($current)
                && ctype_digit($segment)
                && (int) $segment >= count($current)
            ) {
                throw new RefResolutionException(sprintf(
                    'Array index %s out of bounds (length %d)',
                    $segment,
                    count($current),
                ));
            }

            if (false === is_array($current) || false === array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
