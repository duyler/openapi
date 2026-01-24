<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;

use function is_string;

final readonly class PathParser
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
        // Convert template to regex
        $regex = $this->templateToRegex($template);

        if ('' === $regex) {
            throw new PathMismatchException($template, $requestPath);
        }

        $matches = [];
        $matchResult = preg_match($regex, $requestPath, $matches);

        if (false === $matchResult || 1 !== $matchResult) {
            throw new PathMismatchException($template, $requestPath);
        }

        // Extract named parameters
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
        // Replace {param} with named capture groups
        $pattern = preg_replace('/\{([^}]+)\}/', '(?P<$1>[^/]+)', $template);

        if (null === $pattern) {
            // Fallback if preg_replace fails
            return '#^' . preg_quote($template, '#') . '$#';
        }

        return '#^' . $pattern . '$#';
    }
}
