<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Request\PathRegexCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** @internal */
final class PathRegexCacheTest extends TestCase
{
    protected function tearDown(): void
    {
        PathRegexCache::clear();
    }

    #[Test]
    public function get_or_compute_returns_valid_regex(): void
    {
        $regex = PathRegexCache::getOrCompute('/users/{id}');

        self::assertSame(1, preg_match($regex, '/users/123'));
        self::assertSame(0, preg_match($regex, '/posts/123'));
    }

    #[Test]
    public function get_or_compute_returns_same_result_on_repeated_call(): void
    {
        $first = PathRegexCache::getOrCompute('/users/{id}');
        $second = PathRegexCache::getOrCompute('/users/{id}');

        self::assertSame($first, $second);
    }

    #[Test]
    public function get_or_compute_different_templates_return_different_regex(): void
    {
        $users = PathRegexCache::getOrCompute('/users/{id}');
        $posts = PathRegexCache::getOrCompute('/posts/{postId}');

        self::assertNotSame($users, $posts);
    }

    #[Test]
    public function clear_resets_cache(): void
    {
        $before = PathRegexCache::getOrCompute('/users/{id}');

        PathRegexCache::clear();

        $after = PathRegexCache::getOrCompute('/users/{id}');

        self::assertSame($before, $after);
    }
}
