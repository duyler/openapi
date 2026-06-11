<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Link;

use Duyler\OpenApi\Schema\Model\Link;
use Duyler\OpenApi\Schema\Model\Server;

use function array_key_exists;
use function is_array;
use function is_string;
use function preg_match;

final readonly class LinkResolver
{
    /**
     * @param array<string, mixed> $responseData
     *
     * @return array{parameters: array<string, mixed>, requestBody: mixed, server: Server|null}
     */
    public function resolve(Link $link, array $responseData): array
    {
        $parameters = $this->resolveParameters($link->parameters, $responseData);
        $requestBody = $this->resolveValue($link->requestBody, $responseData);
        $server = $link->server;

        return [
            'parameters' => $parameters,
            'requestBody' => $requestBody,
            'server' => $server,
        ];
    }

    /**
     * @param array<string, mixed>|null $parameters
     * @param array<string, mixed> $responseData
     *
     * @return array<string, mixed>
     */
    private function resolveParameters(?array $parameters, array $responseData): array
    {
        if (null === $parameters) {
            return [];
        }

        /** @var array<string, mixed> $resolved */
        $resolved = [];

        foreach ($parameters as $name => $expression) {
            $resolved[$name] = $this->resolveValue($expression, $responseData);
        }

        return $resolved;
    }

    private function resolveValue(mixed $expression, array $responseData): mixed
    {
        if (false === is_string($expression)) {
            return $expression;
        }

        if (1 !== preg_match('/^\$response\.(body|header|query)(?:#(\/.+))?$/', $expression, $matches)) {
            return $expression;
        }

        $source = $matches[1];
        $path = $matches[2] ?? null;

        return match ($source) {
            'body' => $this->extractByPath($responseData, $path),
            'header', 'query' => $expression,
            default => $expression,
        };
    }

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
            if (false === is_array($current) || false === array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }
}
