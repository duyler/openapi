<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link\Internal;

use Duyler\OpenApi\Validator\Exception\RefResolutionException;

use function array_is_list;
use function array_key_exists;
use function array_map;
use function count;
use function ctype_digit;
use function explode;
use function implode;
use function is_array;
use function preg_match;
use function sprintf;
use function str_replace;
use function strtolower;
use function strval;
use function trim;

/**
 * @internal
 */
final readonly class RuntimeExpressionEvaluator
{
    public function resolve(string $expression, ExpressionData $data): mixed
    {
        return match (true) {
            '$url' === $expression => $data->url,
            '$method' === $expression => $data->method,
            '$statusCode' === $expression => $data->statusCode,
            default => $this->resolveRuntimeExpression($expression, $data),
        };
    }

    private function resolveRuntimeExpression(string $expression, ExpressionData $data): mixed
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
                $data,
            );
        }

        if (1 === preg_match(
            '/^\$(?<scope>request|response)\.(?<source>body|header|query)(?:#(?<path>\/.+))?$/',
            $expression,
            $matches,
        )) {
            return $this->extractByPath(
                $this->resolvePointerData($matches['scope'], $matches['source'], $data),
                $matches['path'] ?? null,
            );
        }

        return $expression;
    }

    private function resolveNamedExpression(
        string $scope,
        string $source,
        string $name,
        ExpressionData $data,
    ): mixed {
        return match (true) {
            'request' === $scope && 'path' === $source => $data->pathParams[$name] ?? null,
            'request' === $scope && 'query' === $source => $data->queryParams[$name] ?? null,
            'request' === $scope && 'header' === $source => $this->lookupHeader($data->requestHeaders, $name),
            'response' === $scope && 'query' === $source => $data->queryParams[$name] ?? null,
            'response' === $scope && 'header' === $source => $this->lookupHeader($data->headers, $name),
            default => null,
        };
    }

    private function resolvePointerData(string $scope, string $source, ExpressionData $data): mixed
    {
        return match (true) {
            'request' === $scope && 'body' === $source => $data->requestBody,
            'request' === $scope && 'header' === $source => $data->requestHeaders,
            'request' === $scope && 'query' === $source => $data->queryParams,
            'response' === $scope && 'body' === $source => $data->body,
            'response' === $scope && 'header' === $source => $data->headers,
            'response' === $scope && 'query' === $source => $data->queryParams,
            default => null,
        };
    }

    /**
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

            /** @var mixed $current */
            $current = $current[$segment];
        }

        return $current;
    }
}
