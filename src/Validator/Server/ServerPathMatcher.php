<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;

use function strlen;
use function usort;

use const PHP_URL_PATH;

final readonly class ServerPathMatcher
{
    /** @var list<array{server: Server, basePath: string}> */
    private readonly array $sortedServerPaths;

    /**
     * @param list<Server> $servers
     */
    public function __construct(
        array $servers = [],
        private readonly ServerUrlResolver $resolver = new ServerUrlResolver(),
    ) {
        $sortedServerPaths = $this->resolveServerBasePaths($servers);
        usort($sortedServerPaths, $this->compareByBasePathLength(...));

        $this->sortedServerPaths = $sortedServerPaths;
    }

    /**
     * Matches a request path against precompiled server base paths and strips the matched prefix.
     */
    public function matchPath(string $requestPath): ?ServerPathMatch
    {
        return $this->findMatch($this->sortedServerPaths, $requestPath);
    }

    /**
     * @param list<Server> $servers
     *
     * @return list<array{server: Server, basePath: string}>
     */
    private function resolveServerBasePaths(array $servers): array
    {
        $resolved = [];

        foreach ($servers as $server) {
            $basePath = $this->resolveBasePath($server);

            if (null === $basePath) {
                continue;
            }

            $resolved[] = ['server' => $server, 'basePath' => $basePath];
        }

        return $resolved;
    }

    private function resolveBasePath(Server $server): ?string
    {
        try {
            $resolvedUrl = $this->resolver->resolve($server);
        } catch (ServerVariableException) {
            return null;
        }

        $rawPath = (string) parse_url($resolvedUrl, PHP_URL_PATH);

        $basePath = '/' . ltrim(rtrim($rawPath, '/'), './');

        if ('/' === $basePath) {
            return null;
        }

        return $basePath;
    }

    /**
     * @param array{server: Server, basePath: string} $a
     * @param array{server: Server, basePath: string} $b
     */
    private function compareByBasePathLength(array $a, array $b): int
    {
        return strlen($b['basePath']) <=> strlen($a['basePath']);
    }

    /**
     * @param list<array{server: Server, basePath: string}> $resolved
     */
    private function findMatch(array $resolved, string $requestPath): ?ServerPathMatch
    {
        foreach ($resolved as $entry) {
            $basePath = $entry['basePath'];

            if (str_starts_with($requestPath, $basePath . '/') || $requestPath === $basePath) {
                $stripped = substr($requestPath, strlen($basePath));

                return new ServerPathMatch(
                    strippedPath: '/' . ltrim($stripped, '/'),
                    matchedServer: $entry['server'],
                );
            }
        }

        return null;
    }
}
