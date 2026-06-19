<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Request\PathRegexCache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function preg_match;

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

    #[Test]
    public function dot_in_fixed_part_does_not_match_any_char(): void
    {
        $regex = PathRegexCache::getOrCompute('/v1.0/users');

        self::assertSame(1, preg_match($regex, '/v1.0/users'));
        self::assertSame(0, preg_match($regex, '/v1X0/users'));
        self::assertSame(0, preg_match($regex, '/v1_0/users'));
    }

    #[Test]
    public function plus_in_fixed_part_is_literal_not_quantifier(): void
    {
        $regex = PathRegexCache::getOrCompute('/search+advanced');

        self::assertSame(1, preg_match($regex, '/search+advanced'));
        self::assertSame(0, preg_match($regex, '/searchadvanced'));
    }

    #[Test]
    public function parenthesis_in_fixed_part_is_literal_not_group(): void
    {
        $regex = PathRegexCache::getOrCompute('/query(a)');

        self::assertSame(1, preg_match($regex, '/query(a)'));
        self::assertSame(0, preg_match($regex, '/querya'));
    }

    #[Test]
    public function hash_in_fixed_part_does_not_break_delimiter(): void
    {
        $regex = PathRegexCache::getOrCompute('/path#fragment');

        self::assertSame(1, preg_match($regex, '/path#fragment'));
        self::assertSame(0, preg_match($regex, '/pathXfragment'));
    }

    #[Test]
    public function placeholder_with_suffix_literal_matches_captured_name(): void
    {
        $regex = PathRegexCache::getOrCompute('/files/{name}.json');

        $matches = [];
        preg_match($regex, '/files/report.json', $matches);

        self::assertSame(1, preg_match($regex, '/files/report.json'));
        self::assertSame('report', $matches['name']);
        self::assertSame(0, preg_match($regex, '/files/secret'));
        self::assertSame(0, preg_match($regex, '/files/report.txt'));
    }

    #[Test]
    public function level1_placeholder_matches_value_without_slash(): void
    {
        $regex = PathRegexCache::getOrCompute('/users/{id}');

        $matches = [];
        preg_match($regex, '/users/42', $matches);

        self::assertSame('42', $matches['id']);
        self::assertSame(0, preg_match($regex, '/users/42/details'));
    }

    #[Test]
    public function level2_reserved_expansion_matches_value_with_slash(): void
    {
        $regex = PathRegexCache::getOrCompute('/assets/{+path}');

        $matches = [];
        preg_match($regex, '/assets/img/logo.png', $matches);

        self::assertSame(1, preg_match($regex, '/assets/img/logo.png'));
        self::assertSame('img/logo.png', $matches['path']);
    }

    #[Test]
    public function level1_placeholder_rejects_value_with_slash(): void
    {
        $regex = PathRegexCache::getOrCompute('/assets/{path}');

        self::assertSame(0, preg_match($regex, '/assets/img/logo.png'));
        self::assertSame(1, preg_match($regex, '/assets/img'));
    }

    #[Test]
    public function level2_reserved_expansion_rejects_value_with_query(): void
    {
        $regex = PathRegexCache::getOrCompute('/assets/{+path}');

        self::assertSame(0, preg_match($regex, '/assets/img?version=1'));
        self::assertSame(0, preg_match($regex, '/assets/img#section'));
    }

    #[Test]
    public function multiple_level1_placeholders_capture_independently(): void
    {
        $regex = PathRegexCache::getOrCompute('/users/{userId}/posts/{postId}');

        $matches = [];
        preg_match($regex, '/users/42/posts/7', $matches);

        self::assertSame('42', $matches['userId']);
        self::assertSame('7', $matches['postId']);
    }

    #[Test]
    public function mixed_level1_and_level2_placeholders(): void
    {
        $regex = PathRegexCache::getOrCompute('/{lang}/assets/{+path}');

        $matches = [];
        preg_match($regex, '/en/assets/img/logo.png', $matches);

        self::assertSame('en', $matches['lang']);
        self::assertSame('img/logo.png', $matches['path']);
    }

    #[Test]
    public function invalid_name_starting_with_digit_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid path parameter name');

        PathRegexCache::getOrCompute('/users/{123invalid}');
    }

    #[Test]
    public function invalid_name_starting_with_dollar_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PathRegexCache::getOrCompute('/users/{$var}');
    }

    #[Test]
    public function unsupported_fragment_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported path template operator');

        PathRegexCache::getOrCompute('/path/{#frag}');
    }

    #[Test]
    public function unsupported_query_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported path template operator');

        PathRegexCache::getOrCompute('/path/{?query}');
    }

    #[Test]
    public function unsupported_semicolon_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PathRegexCache::getOrCompute('/path/{;param}');
    }

    #[Test]
    public function empty_placeholder_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        PathRegexCache::getOrCompute('/users/{}');
    }
}
