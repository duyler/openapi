<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
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

        if (0 === count($candidates)) {
            throw new BuilderException(
                sprintf('Operation not found: %s %s', strtoupper($method), $requestPath),
            );
        }

        if (1 === count($candidates)) {
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

            if (null !== $this->pathParser->tryMatchPath($requestPath, $pattern)) {
                $candidates[] = $operation;
            }
        }

        return $candidates;
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
        $normalizedMethod = strtolower($method);

        $op = $pathItem->getOperation($normalizedMethod);

        if (null !== $op) {
            return new Operation($pathPattern, $method);
        }

        if (null !== $pathItem->additionalOperations) {
            foreach ($pathItem->additionalOperations as $opMethod => $operation) {
                if (strtolower($opMethod) === $normalizedMethod) {
                    return new Operation($pathPattern, $method);
                }
            }
        }

        return null;
    }
}
