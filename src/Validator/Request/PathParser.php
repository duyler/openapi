<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;

use function is_string;
use function assert;

final readonly class PathParser
{
    public function __construct(
        private readonly PathRegexCache $pathRegexCache,
    ) {}

    /**
     * Match request path against template
     *
     * @return array<string, string> Parameter values
     */
    public function matchPath(string $requestPath, string $template): array
    {
        return $this->tryMatchPath($requestPath, $template)
            ?? throw new PathMismatchException($template, $requestPath);
    }

    /**
     * Non-throwing version of matchPath for iteration use
     *
     * @return array<string, string>|null Parameter values, or null if no match
     */
    public function tryMatchPath(string $requestPath, string $template): ?array
    {
        $regex = $this->templateToRegex($template);

        assert('' !== $regex);

        $matches = [];
        $matchResult = preg_match($regex, $requestPath, $matches);

        if (false === $matchResult || 1 !== $matchResult) {
            return null;
        }

        $params = [];
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function templateToRegex(string $template): string
    {
        return $this->pathRegexCache->getOrCompute($template);
    }
}
