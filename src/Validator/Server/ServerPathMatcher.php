<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Server;

use Duyler\OpenApi\Schema\Model\Server;

use function strlen;
use function usort;

use function assert;
use function is_string;

use const PHP_URL_PATH;

final readonly class ServerPathMatcher
{
    public function __construct(
        private ServerUrlResolver $resolver = new ServerUrlResolver(),
    ) {}

    /**
     * Matches a request path against server definitions and strips the base path.
     *
     * @param list<Server> $servers
     */
    public function matchPath(array $servers, string $requestPath): ?ServerPathMatch
    {
        /** @var list<array{server: Server, basePath: string}> $resolved */
        $resolved = $this->resolveServerBasePaths($servers);

        usort($resolved, $this->compareByBasePathLength(...));

        return $this->findMatch($resolved, $requestPath);
    }

    private function resolveServerBasePaths(array $servers): array
    {
        $resolved = [];

        foreach ($servers as $server) {
            assert($server instanceof Server);

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

    private function compareByBasePathLength(array $a, array $b): int
    {
        assert(isset($a['basePath'], $b['basePath']));
        assert(is_string($a['basePath']) && is_string($b['basePath']));

        return strlen($b['basePath']) <=> strlen($a['basePath']);
    }

    private function findMatch(array $resolved, string $requestPath): ?ServerPathMatch
    {
        foreach ($resolved as $entry) {
            assert(isset($entry['basePath'], $entry['server']));
            assert(is_string($entry['basePath']) && $entry['server'] instanceof Server);

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
