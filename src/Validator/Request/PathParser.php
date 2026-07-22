<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;

use function array_keys;
use function is_string;

final readonly class PathParser
{
    public function __construct(
        private readonly PathRegexCache $pathRegexCache,
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {}

    /** @return array<string, string> Parameter values */
    public function matchPath(string $requestPath, string $template): array
    {
        return $this->tryMatchPath($requestPath, $template)
            ?? throw new PathMismatchException($template, $requestPath);
    }

    /** @return array<string, string>|null Parameter values, or null if no match */
    public function tryMatchPath(string $requestPath, string $template): ?array
    {
        /** @var non-empty-string $regex */
        $regex = $this->pathRegexCache->getOrCompute($template);

        $matches = [];

        try {
            $matchResult = $this->pregExecutor->match($regex, $requestPath, $matches);
        } catch (PregRuntimeException) {
            return null;
        }

        if (false === $matchResult || 1 !== $matchResult) {
            return null;
        }

        $params = [];
        foreach (array_keys($matches) as $key) {
            if (!is_string($key)) {
                continue;
            }

            /** @var mixed $value */
            $value = $matches[$key];
            $params[$key] = is_string($value) ? $value : '';
        }

        return $params;
    }
}
