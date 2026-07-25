<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Internal;

use Duyler\OpenApi\Schema\Model\PathItem;

use function array_key_exists;
use function assert;
use function count;
use function is_array;

/** @internal */
final readonly class TrieLookup
{
    private const int MAX_TRIE_DEPTH = 32;

    /**
     * @param array<int|string, mixed> $node
     * @param list<string>             $segments
     * @param int<0, max>              $depth
     * @param list<array{template: string, item: PathItem}> $results
     */
    public function lookupTrie(array $node, array $segments, int $depth, array &$results): void
    {
        if ($depth >= self::MAX_TRIE_DEPTH) {
            return;
        }

        if ($depth === count($segments)) {
            /** @var list<array{template: string, item: PathItem}> $templates */
            $templates = $node[TrieBuilder::TEMPLATES_KEY] ?? [];
            foreach ($templates as $template) {
                $results[] = $template;
            }

            return;
        }

        $segment = $segments[$depth];

        if (array_key_exists($segment, $node)) {
            /** @var mixed $child */
            $child = $node[$segment];
            assert(is_array($child));
            $this->lookupTrie($child, $segments, $depth + 1, $results);
        }

        if (array_key_exists(TrieBuilder::PARAM_WILDCARD, $node)) {
            /** @var mixed $child */
            $child = $node[TrieBuilder::PARAM_WILDCARD];
            assert(is_array($child));
            $this->lookupTrie($child, $segments, $depth + 1, $results);
        }
    }
}
