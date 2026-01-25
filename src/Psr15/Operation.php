<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Psr15;

use Override;
use Stringable;

use function assert;
use function sprintf;
use function str_contains;
use function count;
use function is_string;

final readonly class Operation implements Stringable
{
    public function __construct(
        public readonly string $path,
        public readonly string $method,
    ) {}

    #[Override]
    public function __toString(): string
    {
        return sprintf('%s %s', strtoupper($this->method), $this->path);
    }

    public function hasPlaceholders(): bool
    {
        return str_contains($this->path, '{');
    }

    public function countPlaceholders(): int
    {
        preg_match_all('/\{[^}]+\}/', $this->path, $matches);

        return count($matches[0] ?? []);
    }

    public function parseParameters(string $requestPath): array
    {
        $pattern = $this->pathToRegex($this->path);
        assert('' !== $pattern);
        preg_match($pattern, $requestPath, $matches);

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function pathToRegex(string $path): string
    {
        $result = preg_replace('/\{([^}]+)\}/', '(?<$1>[^/]+)', $path);

        return '#^' . ($result ?? $path) . '$#';
    }
}
