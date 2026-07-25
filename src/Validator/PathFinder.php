<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Exception\OperationNotFoundException;
use Duyler\OpenApi\Validator\Internal\CandidatePrioritizer;
use Duyler\OpenApi\Validator\Internal\TrieBuilder;
use Duyler\OpenApi\Validator\Internal\TrieLookup;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\PathRegexCache;

use function count;
use function strtolower;
use function usort;

/**
 * @internal PathFinder is an internal orchestration class; its constructor
 *           signature is not part of the public API. Construct via
 *           OpenApiValidatorBuilder, not directly.
 */
final readonly class PathFinder
{
    /** @var array<int|string, mixed> */
    private array $trie;

    /** @var array<string, int> */
    private array $templateOrder;

    private readonly PathParser $pathParser;

    private readonly TrieLookup $trieLookup;

    private readonly CandidatePrioritizer $prioritizer;

    public function __construct(
        private readonly OpenApiDocument $document,
        PathRegexCache $pathRegexCache = new PathRegexCache(),
        PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $this->pathParser = new PathParser($pathRegexCache, $pregExecutor);
        $builder = new TrieBuilder();
        [$this->trie, $this->templateOrder] = $builder->buildTrie($document->paths?->paths ?? []);
        $this->trieLookup = new TrieLookup();
        $this->prioritizer = new CandidatePrioritizer();
    }

    public function findOperation(string $requestPath, string $method): Operation
    {
        $paths = $this->document->paths?->paths ?? [];

        if ([] === $paths) {
            throw new BuilderException('No paths defined in OpenAPI specification');
        }

        $candidates = $this->findCandidates($requestPath, $method);

        if ([] === $candidates) {
            throw new OperationNotFoundException($requestPath, $method);
        }

        [$operation, $pathParameters] = 1 === count($candidates)
            ? $candidates[0]
            : $this->prioritizer->prioritize($candidates);

        if ([] === $pathParameters) {
            return $operation;
        }

        return new Operation(
            path: $operation->path,
            method: $operation->method,
            operationId: $operation->operationId,
            pathParameters: $pathParameters,
            schemaOperation: $operation->schemaOperation,
        );
    }

    /**
     * @return array<int, array{0: Operation, 1: array<string, string>}>
     */
    private function findCandidates(string $requestPath, string $method): array
    {
        $matches = [];
        $segments = explode('/', trim($requestPath, '/'));
        $this->trieLookup->lookupTrie($this->trie, $segments, 0, $matches);

        /** @var array<string, int> $templateOrder */
        $templateOrder = $this->templateOrder;
        usort($matches, static function (array $a, array $b) use ($templateOrder): int {
            /** @var string $aTemplate */
            $aTemplate = $a['template'];
            /** @var string $bTemplate */
            $bTemplate = $b['template'];

            return $templateOrder[$aTemplate] <=> $templateOrder[$bTemplate];
        });
        $candidates = [];
        foreach ($matches as ['template' => $template, 'item' => $pathItem]) {
            $pathParameters = $this->pathParser->tryMatchPath($requestPath, $template);
            if (null === $pathParameters) {
                continue;
            }

            $operation = $this->getOperation($pathItem, $method, $template);
            if (null === $operation) {
                continue;
            }

            $candidates[] = [$operation, $pathParameters];
        }

        return $candidates;
    }

    private function getOperation(PathItem $pathItem, string $method, string $pathPattern): ?Operation
    {
        $normalizedMethod = strtolower($method);

        $op = $pathItem->getOperation($normalizedMethod);

        if (null !== $op) {
            return new Operation(
                path: $pathPattern,
                method: $method,
                operationId: $op->operationId,
                schemaOperation: $op,
            );
        }

        if (null !== $pathItem->additionalOperations) {
            foreach ($pathItem->additionalOperations as $opMethod => $additionalOp) {
                if (strtolower($opMethod) === $normalizedMethod) {
                    return new Operation(
                        path: $pathPattern,
                        method: $method,
                        operationId: $additionalOp->operationId,
                        schemaOperation: $additionalOp,
                    );
                }
            }
        }

        return null;
    }
}
