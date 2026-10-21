<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Security;

use Duyler\OpenApi\Validator\Internal\TrieLookup;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @internal
 */
final class PathFinderDepthLimitTest extends TestCase
{
    #[Test]
    public function max_trie_depth_constant_exists_with_expected_value(): void
    {
        $reflection = new ReflectionClass(TrieLookup::class);

        self::assertTrue(
            $reflection->hasConstant('MAX_TRIE_DEPTH'),
            'TrieLookup must define MAX_TRIE_DEPTH',
        );

        $value = $reflection->getConstant('MAX_TRIE_DEPTH');

        self::assertIsInt($value);
        self::assertSame(
            32,
            $value,
            'TrieLookup::MAX_TRIE_DEPTH must equal 32 to bound recursion in lookupTrie',
        );
    }

    #[Test]
    public function lookup_trie_method_accepts_depth_parameter(): void
    {
        $reflection = new ReflectionClass(TrieLookup::class);
        $method = $reflection->getMethod('lookupTrie');

        $parameterNames = array_map(
            static fn($parameter) => $parameter->getName(),
            $method->getParameters(),
        );

        self::assertContains(
            'depth',
            $parameterNames,
            'TrieLookup::lookupTrie must accept a $depth parameter so recursion can be bounded',
        );
    }
}
