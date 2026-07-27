<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use Duyler\OpenApi\Schema\Model\PathItem;

use function assert;
use function count;
use function explode;
use function is_array;
use function str_ends_with;
use function str_starts_with;
use function trim;

/** @internal */
final readonly class TrieBuilder
{
    public const string PARAM_WILDCARD = '*';

    public const string TEMPLATES_KEY = "\0__templates__\0";

    /**
     * @param array<string, PathItem> $paths
     *
     * @return array{0: array<int|string, mixed>, 1: array<string, int>}
     */
    public function buildTrie(array $paths): array
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

    private function isParameter(string $segment): bool
    {
        return str_starts_with($segment, '{') && str_ends_with($segment, '}');
    }
}
