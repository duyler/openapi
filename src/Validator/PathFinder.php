<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Builder\Exception\BuilderException;
use Duyler\OpenApi\Schema\Model\PathItem;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Request\PathParser;
use Duyler\OpenApi\Validator\Request\PathRegexCache;

use function array_key_exists;
use function array_keys;
use function assert;
use function count;
use function is_array;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;
use function usort;

/**
 * @internal PathFinder is an internal orchestration class; its constructor
 *           signature is not part of the public API. Construct via
 *           OpenApiValidatorBuilder, not directly.
 */
final readonly class PathFinder
{
    private const string PARAM_WILDCARD = '*';
    private const string TEMPLATES_KEY = "\0__templates__\0";

    /** @var array<int|string, mixed> */
    private array $trie;

    /** @var array<string, int> */
    private array $templateOrder;

    private readonly PathParser $pathParser;

    public function __construct(
        private readonly OpenApiDocument $document,
        PathRegexCache $pathRegexCache = new PathRegexCache(),
    ) {
        $this->pathParser = new PathParser($pathRegexCache);
        [$this->trie, $this->templateOrder] = $this->buildTrie($document->paths?->paths ?? []);
    }

    public function findOperation(string $requestPath, string $method): Operation
    {
        $paths = $this->document->paths?->paths ?? [];

        if ([] === $paths) {
            throw new BuilderException('No paths defined in OpenAPI specification');
        }

        $candidates = $this->findCandidates($requestPath, $method);

        if ([] === $candidates) {
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
        $segments = explode('/', trim($requestPath, '/'));
        $matches = $this->lookupTrie($this->trie, $segments, 0);

        usort(
            $matches,
            function (array $a, array $b): int {
                /** @var string $templateA */
                $templateA = $a['template'];
                /** @var string $templateB */
                $templateB = $b['template'];

                return $this->templateOrder[$templateA] <=> $this->templateOrder[$templateB];
            },
        );

        foreach ($matches as ['template' => $template, 'item' => $pathItem]) {
            $operation = $this->getOperation($pathItem, $method, $template);
            if (null === $operation) {
                continue;
            }

            if (null !== $this->pathParser->tryMatchPath($requestPath, $template)) {
                $candidates[] = $operation;
            }
        }

        return $candidates;
    }

    /**
     * @param array<string, PathItem> $paths
     *
     * @return array{0: array<int|string, mixed>, 1: array<string, int>}
     */
    private function buildTrie(array $paths): array
    {
        $trie = [];
        $templateOrder = [];
        $order = 0;

        foreach ($paths as $template => $pathItem) {
            $templateOrder[$template] = $order;
            ++$order;
            $trie = $this->insertTemplate($trie, $template, $pathItem);
        }

        return [$trie, $templateOrder];
    }

    /**
     * @param array<int|string, mixed> $node
     */
    private function insertTemplate(array $node, string $template, PathItem $pathItem): array
    {
        $segments = explode('/', trim($template, '/'));

        return $this->insertSegments($node, $segments, 0, $template, $pathItem);
    }

    /**
     * @param array<int|string, mixed> $node
     * @param list<string>             $segments
     */
    private function insertSegments(array $node, array $segments, int $depth, string $template, PathItem $pathItem): array
    {
        if ($depth === count($segments)) {
            $templates = $node[self::TEMPLATES_KEY] ?? [];
            assert(is_array($templates));
            $templates[] = ['template' => $template, 'item' => $pathItem];
            $node[self::TEMPLATES_KEY] = $templates;

            return $node;
        }

        $segment = $segments[$depth];
        $key = $this->isParameter($segment) ? self::PARAM_WILDCARD : $segment;
        $child = $node[$key] ?? [];
        assert(is_array($child));

        $node[$key] = $this->insertSegments($child, $segments, $depth + 1, $template, $pathItem);

        return $node;
    }

    /**
     * @param array<int|string, mixed> $node
     * @param list<string>             $segments
     *
     * @return list<array{template: string, item: PathItem}>
     */
    private function lookupTrie(array $node, array $segments, int $depth): array
    {
        if ($depth === count($segments)) {
            /** @var list<array{template: string, item: PathItem}> $templates */
            $templates = $node[self::TEMPLATES_KEY] ?? [];

            return $templates;
        }

        $segment = $segments[$depth];
        $candidates = [];

        if (array_key_exists($segment, $node)) {
            /** @var mixed $child */
            $child = $node[$segment];
            assert(is_array($child));
            $candidates = [...$candidates, ...$this->lookupTrie($child, $segments, $depth + 1)];
        }

        if (array_key_exists(self::PARAM_WILDCARD, $node)) {
            /** @var mixed $child */
            $child = $node[self::PARAM_WILDCARD];
            assert(is_array($child));
            $candidates = [...$candidates, ...$this->lookupTrie($child, $segments, $depth + 1)];
        }

        return $candidates;
    }

    private function isParameter(string $segment): bool
    {
        return str_starts_with($segment, '{') && str_ends_with($segment, '}');
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
            foreach (array_keys($pathItem->additionalOperations) as $opMethod) {
                if (strtolower($opMethod) === $normalizedMethod) {
                    return new Operation($pathPattern, $method);
                }
            }
        }

        return null;
    }
}
