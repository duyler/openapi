<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;
use Duyler\OpenApi\Schema\OpenApiDocument;

use function preg_match;
use function sprintf;

final readonly class ServerUrlValidator
{
    public function __construct(
        private readonly ServerUrlResolver $resolver = new ServerUrlResolver(),
    ) {}

    /**
     * Validates that a request URL matches one of the declared servers.
     *
     * @param OpenApiDocument $document The parsed OpenAPI document containing server definitions
     * @param string $requestUrl The full request URL to validate
     * @param array<string, string> $variableOverrides Optional server variable overrides
     *
     * @throws ServerUrlMismatchException If the request URL doesn't match any declared server
     */
    public function validate(
        OpenApiDocument $document,
        string $requestUrl,
        array $variableOverrides = [],
    ): void {
        $servers = $this->resolveServers($document, $variableOverrides);

        if ([] === $servers) {
            return;
        }

        foreach ($servers as $resolvedUrl) {
            if ($this->urlMatches($resolvedUrl, $requestUrl)) {
                return;
            }
        }

        throw new ServerUrlMismatchException(
            sprintf(
                'Request URL "%s" does not match any declared server: %s',
                $requestUrl,
                implode(', ', $servers),
            ),
        );
    }

    /**
     * Checks if a request URL matches any declared server pattern.
     *
     * @param OpenApiDocument $document The parsed OpenAPI document
     * @param string $requestUrl The request URL to check
     * @param array<string, string> $variableOverrides Optional server variable overrides
     */
    public function isValid(
        OpenApiDocument $document,
        string $requestUrl,
        array $variableOverrides = [],
    ): bool {
        $servers = $this->resolveServers($document, $variableOverrides);

        if ([] === $servers) {
            return true;
        }

        foreach ($servers as $resolvedUrl) {
            if ($this->urlMatches($resolvedUrl, $requestUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns all matching server definitions for the given request URL.
     *
     * @return list<Server>
     */
    public function findMatchingServers(
        OpenApiDocument $document,
        string $requestUrl,
        array $variableOverrides = [],
    ): array {
        /** @var array<string, string> $typedOverrides */
        $typedOverrides = $variableOverrides;
        $serverDefinitions = $document->servers?->servers ?? [];
        $matches = [];

        foreach ($serverDefinitions as $server) {
            try {
                $resolvedUrl = $this->resolver->resolve($server, $typedOverrides);

                if ($this->urlMatches($resolvedUrl, $requestUrl)) {
                    $matches[] = $server;
                }
            } catch (ServerVariableException) {
                continue;
            }
        }

        return $matches;
    }

    /**
     * @return list<string>
     */
    private function resolveServers(OpenApiDocument $document, array $variableOverrides): array
    {
        /** @var array<string, string> $typedOverrides */
        $typedOverrides = $variableOverrides;
        $serverDefinitions = $document->servers?->servers ?? [];
        $resolved = [];

        foreach ($serverDefinitions as $server) {
            try {
                $resolved[] = $this->resolver->resolve($server, $typedOverrides);
            } catch (ServerVariableException) {
                continue;
            }
        }

        return $resolved;
    }

    private function urlMatches(string $serverUrl, string $requestUrl): bool
    {
        if ($serverUrl === $requestUrl) {
            return true;
        }

        if (true === str_starts_with($requestUrl, $serverUrl)) {
            return true;
        }

        $pattern = '#^' . preg_quote($serverUrl, '#') . '($|/)#';

        return 1 === preg_match($pattern, $requestUrl);
    }
}
