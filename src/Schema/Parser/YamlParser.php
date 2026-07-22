<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;
use Symfony\Component\Yaml\Yaml;

use function array_keys;
use function count;
use function explode;
use function is_array;
use function is_string;
use function ltrim;
use function min;
use function strlen;

final class YamlParser extends OpenApiBuilder
{
    public const int DEFAULT_MAX_SPEC_BYTES = 1_048_576;

    public const int DEFAULT_MAX_SPEC_DEPTH = 100;

    /**
     * Conservative caps for YAML anchor/alias expansion bombs
     * (billion-laughs attack, CWE-400, CWE-770). Real OpenAPI specs
     * typically use fewer than 20 anchors and 50 aliases for schema
     * deduplication; these caps allow legitimate deduplication while
     * rejecting exponential blowup before the Symfony YAML parser
     * materialises the expanded document.
     */
    public const int MAX_ANCHORS = 100;

    public const int MAX_ALIASES = 1000;

    public const int MAX_ALIAS_DEPTH = 10;

    private readonly PregExecutor $pregExecutor;

    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
        private readonly int $maxSpecBytes = self::DEFAULT_MAX_SPEC_BYTES,
        private readonly int $maxSpecDepth = self::DEFAULT_MAX_SPEC_DEPTH,
        ?PregExecutor $pregExecutor = null,
    ) {
        parent::__construct($deprecationLogger);
        $this->pregExecutor = $pregExecutor ?? new PregExecutor();
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        if (strlen($content) > $this->maxSpecBytes) {
            throw SpecTooLargeException::forSize($this->maxSpecBytes, strlen($content));
        }

        $this->assertNoAnchorBomb($content);

        /** @var mixed $data */
        $data = Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        if (is_array($data)) {
            $depth = $this->calculateDepth($data);
            if ($depth > $this->maxSpecDepth) {
                throw SpecTooLargeException::forDepth($depth, $this->maxSpecDepth);
            }
        }

        return $data;
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'YAML';
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function calculateDepth(array $data, int $current = 0): int
    {
        $maxChildDepth = $current;

        foreach ($data as $value) {
            /** @var mixed $value */
            if (!is_array($value)) {
                continue;
            }

            $childDepth = $this->calculateDepth($value, $current + 1);
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth;
    }

    private function assertNoAnchorBomb(string $content): void
    {
        $anchorCount = $this->countAnchors($content);
        if ($anchorCount > self::MAX_ANCHORS) {
            throw SpecTooLargeException::forAnchorCount(self::MAX_ANCHORS, $anchorCount);
        }

        $aliasCount = $this->countAliases($content);
        if ($aliasCount > self::MAX_ALIASES) {
            throw SpecTooLargeException::forAliasCount(self::MAX_ALIASES, $aliasCount);
        }

        $aliasDepth = $this->estimateAliasNestingDepth($content);
        if ($aliasDepth > self::MAX_ALIAS_DEPTH) {
            throw SpecTooLargeException::forAliasDepth(self::MAX_ALIAS_DEPTH, $aliasDepth);
        }
    }

    private function countAnchors(string $content): int
    {
        $result = $this->pregExecutor->matchAll('/(?<![&*])&[^ \t,\[\]\{\}\n]+/u', $content);

        return false === $result ? 0 : $result;
    }

    private function countAliases(string $content): int
    {
        $result = $this->pregExecutor->matchAll('/(?<!&)\*[^ \t,\[\]\{\}\n]+/u', $content);

        return false === $result ? 0 : $result;
    }

    private function estimateAliasNestingDepth(string $content): int
    {
        $lines = explode("\n", $content);
        $anchors = $this->findAnchorDeclarations($lines);

        if ([] === $anchors) {
            return 0;
        }

        $dag = $this->buildAnchorChainDag($lines, $anchors);

        return $this->computeMaxChainDepth($dag, array_keys($anchors));
    }

    /**
     * @param list<string> $lines
     *
     * @return array<string, array{line: int, indent: int}>
     */
    private function findAnchorDeclarations(array $lines): array
    {
        $anchors = [];
        foreach ($lines as $idx => $line) {
            $matches = [];
            $this->pregExecutor->matchAll('/&([^ \t,\[\]\{\}\n]+)/u', $line, $matches);
            foreach ($this->capturedStrings($matches) as $name) {
                $anchors[$name] ??= ['line' => $idx, 'indent' => strlen($line) - strlen(ltrim($line))];
            }
        }

        return $anchors;
    }

    /**
     * @param list<string>                     $lines
     * @param array<string, array{line: int, indent: int}> $anchors
     *
     * @return array<string, array<string, true>>
     */
    private function buildAnchorChainDag(array $lines, array $anchors): array
    {
        $dag = [];
        $lineCount = count($lines);

        foreach ($anchors as $parent => $parentInfo) {
            $endLine = $this->findValueRangeEnd($anchors, $parentInfo, $lineCount);

            for ($i = $parentInfo['line']; $i <= $endLine; ++$i) {
                $aliasMatches = [];
                $this->pregExecutor->matchAll('/\*([^ \t,\[\]\{\}\n]+)/u', $lines[$i], $aliasMatches);
                foreach ($this->capturedStrings($aliasMatches) as $alias) {
                    if ($alias !== $parent && isset($anchors[$alias])) {
                        $dag[$parent][$alias] = true;
                    }
                }
            }
        }

        return $dag;
    }

    /**
     * @param array<string, array{line: int, indent: int}> $anchors
     * @param array{line: int, indent: int}                $parent
     */
    private function findValueRangeEnd(array $anchors, array $parent, int $lineCount): int
    {
        $endLine = $lineCount - 1;
        foreach ($anchors as $info) {
            if ($info['line'] > $parent['line'] && $info['indent'] <= $parent['indent']) {
                $endLine = min($endLine, $info['line'] - 1);
            }
        }

        return $endLine;
    }

    /**
     * @param array<string, array<string, true>> $dag
     * @param list<string>                       $nodes
     */
    private function computeMaxChainDepth(array $dag, array $nodes): int
    {
        $cache = [];
        $max = 0;
        foreach ($nodes as $start) {
            $depth = $this->chainDepthDfs($start, $dag, $cache, []);
            if ($depth > $max) {
                $max = $depth;
            }
        }

        return $max;
    }

    /**
     * @param array<string, array<string, true>> $dag
     * @param array<string, int>                 $cache
     * @param array<string, true>                $visiting
     */
    private function chainDepthDfs(string $node, array $dag, array &$cache, array $visiting): int
    {
        if (isset($cache[$node])) {
            return $cache[$node];
        }

        if (isset($visiting[$node])) {
            return 0;
        }

        $visiting[$node] = true;
        $maxChild = 0;
        foreach (array_keys($dag[$node] ?? []) as $child) {
            $childDepth = $this->chainDepthDfs($child, $dag, $cache, $visiting);
            if ($childDepth > $maxChild) {
                $maxChild = $childDepth;
            }
        }

        return $cache[$node] = $maxChild + 1;
    }

    /**
     * @param array<array-key, mixed> $matches output of PregExecutor::matchAll
     *
     * @return list<string> first capture group values, non-string entries skipped
     */
    private function capturedStrings(array $matches): array
    {
        $group = $matches[1] ?? [];
        if (!is_array($group)) {
            return [];
        }

        $captured = [];
        array_walk($group, static function (mixed $value) use (&$captured): void {
            if (is_string($value)) {
                $captured[] = $value;
            }
        });

        return $captured;
    }
}
