<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Validator\PathFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * P-034 regression: PathFinder::lookupTrie must enforce a depth limit
 * (MAX_TRIE_DEPTH = 32) to prevent algorithmic amplification on
 * deeply-branched spec tries. Task 20 introduced the cap alongside the
 * by-reference accumulator; this test pins the contract so it cannot
 * regress silently.
 *
 * @internal
 */
final class PathFinderDepthLimitTest extends TestCase
{
    #[Test]
    public function max_trie_depth_constant_exists_with_expected_value(): void
    {
        $reflection = new ReflectionClass(PathFinder::class);

        self::assertTrue(
            $reflection->hasConstant('MAX_TRIE_DEPTH'),
            'PathFinder must define MAX_TRIE_DEPTH (P-034)',
        );

        $value = $reflection->getConstant('MAX_TRIE_DEPTH');

        self::assertIsInt($value);
        self::assertSame(
            32,
            $value,
            'PathFinder::MAX_TRIE_DEPTH must equal 32 to bound recursion in lookupTrie',
        );
    }

    #[Test]
    public function lookup_trie_method_accepts_depth_parameter(): void
    {
        $reflection = new ReflectionClass(PathFinder::class);
        $method = $reflection->getMethod('lookupTrie');

        $parameterNames = array_map(
            static fn($parameter) => $parameter->getName(),
            $method->getParameters(),
        );

        self::assertContains(
            'depth',
            $parameterNames,
            'PathFinder::lookupTrie must accept a $depth parameter so recursion can be bounded (P-034)',
        );
    }
}
