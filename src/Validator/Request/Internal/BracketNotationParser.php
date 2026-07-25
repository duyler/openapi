<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

use Duyler\OpenApi\Validator\Exception\InvalidParameterException;

use function array_key_last;
use function array_slice;
use function count;
use function sprintf;
use function substr_count;
use function is_array;

/** @internal */
final readonly class BracketNotationParser
{
    private const int MAX_NESTING_DEPTH = 64;

    /**
     * @param array<array-key, mixed> $tree
     *
     * @return array<array-key, mixed>
     */
    public function insertNested(array $tree, string $key, string $value): array
    {
        if (1 !== preg_match('/^(?<root>[^\[]+)(?<rest>.*)$/', $key, $m)) {
            return $tree;
        }

        /** @var string $rootName */
        $rootName = $m['root'];
        /** @var string $rest */
        $rest = $m['rest'];

        if ('' === $rest) {
            $tree[$rootName] = $value;

            return $tree;
        }

        $this->assertDepth($rootName, $rest);

        $segments = $this->extractSegments($rootName, $rest);

        return $this->assignSegments($tree, $segments, $value);
    }

    /**
     * @param array<array-key, mixed>  $node
     * @param non-empty-list<string>   $segments
     *
     * @return array<array-key, mixed>
     */
    public function assignSegments(array $node, array $segments, string $value): array
    {
        $segment = $segments[0];
        /** @var list<string> $remaining */
        $remaining = array_slice($segments, 1);

        if ('' === $segment) {
            return $this->assignEmptySegment($node, $remaining, $value);
        }

        if ([] === $remaining) {
            $node[$segment] = $value;

            return $node;
        }

        if (false === is_array($node[$segment] ?? null)) {
            $node[$segment] = [];
        }
        /** @var array<array-key, mixed> $child */
        $child = $node[$segment];
        $node[$segment] = $this->assignSegments($child, $remaining, $value);

        return $node;
    }

    private function assertDepth(string $rootName, string $rest): void
    {
        if (substr_count($rest, '[') > self::MAX_NESTING_DEPTH) {
            throw new InvalidParameterException(
                $rootName,
                sprintf('Maximum query parameter nesting depth of %d exceeded', self::MAX_NESTING_DEPTH),
            );
        }
    }

    /**
     * @return non-empty-list<string>
     */
    private function extractSegments(string $rootName, string $rest): array
    {
        $segments = [$rootName];
        if (preg_match_all('/\[(?<key>[^\[\]]*)\]/', $rest, $bracketMatches) > 0) {
            /** @var list<string> $bracketKeys */
            $bracketKeys = $bracketMatches['key'];
            foreach ($bracketKeys as $bk) {
                $segments[] = $bk;
            }
        }

        if (count($segments) > self::MAX_NESTING_DEPTH) {
            throw new InvalidParameterException(
                $rootName,
                sprintf('Maximum query parameter nesting depth of %d exceeded', self::MAX_NESTING_DEPTH),
            );
        }

        return $segments;
    }

    /**
     * @param array<array-key, mixed>  $node
     * @param list<string>             $remaining
     *
     * @return array<array-key, mixed>
     */
    private function assignEmptySegment(array $node, array $remaining, string $value): array
    {
        if ([] === $remaining) {
            $node[] = $value;

            return $node;
        }

        $node[] = [];
        $lastKey = array_key_last($node);
        /** @var array<array-key, mixed> $child */
        $child = $node[$lastKey];
        $node[$lastKey] = $this->assignSegments($child, $remaining, $value);

        return $node;
    }
}
