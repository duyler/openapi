<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Psr\Log\LoggerInterface;

use function array_keys;
use function is_string;
use function strlen;

final readonly class PathParser
{
    public function __construct(
        private readonly PathRegexCache $pathRegexCache,
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
        private readonly ?LoggerInterface $logger = null,
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
        } catch (PregRuntimeException $e) {
            $this->logger?->debug('PCRE failure during path parsing', [
                'pattern' => $regex,
                'subject_length' => strlen($requestPath),
                'exception' => $e,
            ]);

            return null;
        }

        if (false === $matchResult || 1 !== $matchResult) {
            return null;
        }

        $params = [];
        foreach (array_keys($matches) as $key) {
            if (false === is_string($key)) {
                continue;
            }

            /** @var mixed $value */
            $value = $matches[$key];
            $params[$key] = is_string($value) ? $value : '';
        }

        return $params;
    }
}
