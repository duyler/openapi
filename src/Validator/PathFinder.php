<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\PathMismatchException;
use Duyler\OpenApi\Validator\Request\PathParser;

use function count;
use function sprintf;
use function strtolower;
use function strtoupper;
use function usort;

final readonly class PathFinder
{
    public function __construct(
        private readonly OpenApiDocument $document,
        private readonly PathParser $pathParser = new PathParser(),
    ) {}

    public function findOperation(string $requestPath, string $method): Operation
    {
        $paths = $this->document->paths?->paths ?? [];

        if ([] === $paths) {
            throw new BuilderException('No paths defined in OpenAPI specification');
        }

        $candidates = $this->findCandidates($requestPath, $method);

        if (count($candidates) === 0) {
            throw new BuilderException(
                sprintf('Operation not found: %s %s', strtoupper($method), $requestPath),
            );
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        return $this->prioritizeCandidates($candidates);
    }

    /**
     * @return array<int, Operation>
     */
    private function findCandidates(string $requestPath, string $method): array
    {
        $candidates = [];
        $paths = $this->document->paths?->paths ?? [];

        foreach ($paths as $pattern => $pathItem) {
            $operation = $this->getOperation($pathItem, $method, $pattern);
            if (null === $operation) {
                continue;
            }

            if ($this->pathMatches($pattern, $requestPath)) {
                $candidates[] = $operation;
            }
        }

        return $candidates;
    }

    private function pathMatches(string $pattern, string $path): bool
    {
        try {
            $this->pathParser->matchPath($path, $pattern);
            return true;
        } catch (PathMismatchException) {
            return false;
        }
    }

    /**
     * @param array<int, Operation> $candidates
     */
    private function prioritizeCandidates(array $candidates): Operation
    {
        usort($candidates, fn(Operation $a, Operation $b): int => $a->countPlaceholders() <=> $b->countPlaceholders());

        return $candidates[0];
    }

    private function getOperation(PathItem $pathItem, string $method, string $pathPattern): ?Operation
    {
        $op = match (strtolower($method)) {
            'get' => $pathItem->get,
            'post' => $pathItem->post,
            'put' => $pathItem->put,
            'patch' => $pathItem->patch,
            'delete' => $pathItem->delete,
            'options' => $pathItem->options,
            'head' => $pathItem->head,
            'trace' => $pathItem->trace,
            default => null,
        };

        if (null !== $op) {
            return new Operation($pathPattern, $method);
        }

        return null;
    }
}
