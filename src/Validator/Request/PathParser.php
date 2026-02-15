<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;

use function is_string;
use function assert;

readonly class PathParser
{
    /**
     * Extract parameter names from path template
     *
     * @return array<int, string>
     */
    public function parseParameters(string $pathTemplate): array
    {
        preg_match_all('/\{([^}]+)\}/', $pathTemplate, $matches);

        return $matches[1] ?? [];
    }

    /**
     * Match request path against template
     *
     * @return array<string, string> Parameter values
     */
    public function matchPath(string $requestPath, string $template): array
    {
        $regex = $this->templateToRegex($template);

        assert('' !== $regex);

        $matches = [];
        $matchResult = preg_match($regex, $requestPath, $matches);

        if (false === $matchResult || 1 !== $matchResult) {
            throw new PathMismatchException($template, $requestPath);
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
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $template);

        assert(null !== $pattern);

        return '#^' . $pattern . '$#';
    }
}
