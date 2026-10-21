<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Parser\Internal\ChainDepthMemo;
use Duyler\OpenApi\Schema\Parser\Internal\SpecSizeGuard;
use Duyler\OpenApi\Validator\Exception\SpecTooLargeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Override;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
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
    public const int DEFAULT_MAX_SPEC_BYTES = SpecSizeGuard::DEFAULT_MAX_SPEC_BYTES;

    public const int DEFAULT_MAX_SPEC_DEPTH = SpecSizeGuard::DEFAULT_MAX_SPEC_DEPTH;

    public const int MAX_ANCHORS = 100;

    public const int MAX_ALIASES = 1000;

    public const int MAX_ALIAS_DEPTH = 10;

    private const string ANCHOR_NAME_PATTERN = '[^ \t,\[\]\{\}\n]+';

    private readonly PregExecutor $pregExecutor;

    private readonly SpecSizeGuard $specSizeGuard;

    public function __construct(
        ?DeprecationLogger $deprecationLogger = null,
        int $maxSpecBytes = self::DEFAULT_MAX_SPEC_BYTES,
        int $maxSpecDepth = self::DEFAULT_MAX_SPEC_DEPTH,
        ?PregExecutor $pregExecutor = null,
    ) {
        parent::__construct($deprecationLogger);
        $this->pregExecutor = $pregExecutor ?? new PregExecutor();
        $this->specSizeGuard = new SpecSizeGuard($maxSpecBytes, $maxSpecDepth);
    }

    #[Override]
    protected function parseContent(string $content): mixed
    {
        $this->specSizeGuard->assertSize($content);

        $this->assertNoAnchorBomb($content);

        /** @var mixed $data */
        $data = Yaml::parse($content, Yaml::PARSE_EXCEPTION_ON_INVALID_TYPE);

        $this->specSizeGuard->assertDepth($data);

        return $data;
    }

    #[Override]
    protected function getFormatName(): string
    {
        return 'YAML';
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
        $result = $this->pregExecutor->matchAll('/(?<![&*])&' . self::ANCHOR_NAME_PATTERN . '/u', $content);

        return false === $result ? 0 : $result;
    }

    private function countAliases(string $content): int
    {
        $result = $this->pregExecutor->matchAll('/(?<!&)\*' . self::ANCHOR_NAME_PATTERN . '/u', $content);

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
     * @return array<string, array{line: int, indent: int}>
     */
    private function findAnchorDeclarations(array $lines): array
    {
        $anchors = [];
        foreach ($lines as $idx => $line) {
            $matches = [];
            $this->pregExecutor->matchAll('/&(?<anchor>' . self::ANCHOR_NAME_PATTERN . ')/u', $line, $matches);

            $anchorCaptures = $matches['anchor'] ?? [];

            if (false === is_array($anchorCaptures)) {
                continue;
            }

            /** @var array<array-key, string> $anchorCaptures */
            $anchorCaptures = array_filter($anchorCaptures, is_string(...));

            foreach ($anchorCaptures as $name) {
                $anchors[$name] ??= ['line' => $idx, 'indent' => strlen($line) - strlen(ltrim($line))];
            }
        }

        return $anchors;
    }

    /**
     * @param list<string> $lines
     * @param array<string, array{line: int, indent: int}> $anchors
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
                $this->pregExecutor->matchAll('/\*(?<alias>' . self::ANCHOR_NAME_PATTERN . ')/u', $lines[$i], $aliasMatches);

                $aliasCaptures = $aliasMatches['alias'] ?? [];

                if (false === is_array($aliasCaptures)) {
                    continue;
                }

                /** @var array<array-key, string> $aliasCaptures */
                $aliasCaptures = array_filter($aliasCaptures, is_string(...));

                foreach ($aliasCaptures as $alias) {
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
     * @param array{line: int, indent: int} $parent
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
     * @param list<string> $nodes
     */
    private function computeMaxChainDepth(array $dag, array $nodes): int
    {
        $memo = new ChainDepthMemo();
        $max = 0;
        foreach ($nodes as $start) {
            $depth = $this->chainDepthDfs($start, $dag, $memo, []);
            if ($depth > $max) {
                $max = $depth;
            }
        }

        return $max;
    }

    /**
     * @param array<string, array<string, true>> $dag
     * @param array<string, true> $visiting
     */
    private function chainDepthDfs(string $node, array $dag, ChainDepthMemo $memo, array $visiting): int
    {
        $memoized = $memo->get($node);

        if (null !== $memoized) {
            return $memoized;
        }

        if (isset($visiting[$node])) {
            return 0;
        }

        $visiting[$node] = true;
        $maxChild = 0;
        foreach (array_keys($dag[$node] ?? []) as $child) {
            $childDepth = $this->chainDepthDfs($child, $dag, $memo, $visiting);
            if ($childDepth > $maxChild) {
                $maxChild = $childDepth;
            }
        }

        $depth = $maxChild + 1;
        $memo->set($node, $depth);

        return $depth;
    }
}
