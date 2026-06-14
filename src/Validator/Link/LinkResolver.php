<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Validator\Exception\RefResolutionException;

use function array_key_exists;
use function array_is_list;
use function count;
use function ctype_digit;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;

final readonly class LinkResolver
{
    /**
     * @return array{parameters: array<string, mixed>, requestBody: mixed, server: Server|null}
     */
    public function resolve(Link $link, LinkContext $context): array
    {
        $parameters = $this->resolveParameters($link->parameters, $context);
        $requestBody = $this->resolveValue($link->requestBody, $context);
        $server = $link->server;

        return [
            'parameters' => $parameters,
            'requestBody' => $requestBody,
            'server' => $server,
        ];
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

        if ('$url' === $expression) {
            return $context->url;
        }

        if ('$method' === $expression) {
            return $context->method;
        }

        if ('$statusCode' === $expression) {
            return $context->statusCode;
        }

        if (1 !== preg_match('/^\$response\.(?<source>body|header|query)(?:#(?<path>\/.+))?$/', $expression, $matches)) {
            return $expression;
        }

        $source = $matches['source'];
        $path = $matches['path'] ?? null;

        return match ($source) {
            'body' => $this->extractByPath($context->body, $path),
            'header' => $this->extractByPath($context->headers, $path),
            'query' => $this->extractByPath($context->queryParams, $path),
            default => $expression,
        };
    }

    /**
     * @param array<string|int, mixed> $data
     */
    private function extractByPath(array $data, ?string $path): mixed
    {
        if (null === $path || '' === $path) {
            return $data;
        }

        /** @var list<string> $segments */
        $segments = explode('/', trim($path, '/'));

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
