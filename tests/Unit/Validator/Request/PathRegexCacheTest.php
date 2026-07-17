<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Request;

use Duyler\OpenApi\Validator\Request\PathRegexCache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function preg_match;
use function sprintf;
use function count;

/** @internal */
final class PathRegexCacheTest extends TestCase
{
    private PathRegexCache $cache;

    protected function setUp(): void
    {
        $this->cache = new PathRegexCache();
    }

    protected function tearDown(): void
    {
        unset($this->cache);
    }

    #[Test]
    public function get_or_compute_returns_valid_regex(): void
    {
        $regex = $this->cache->getOrCompute('/users/{id}');

        self::assertSame(1, preg_match($regex, '/users/123'));
        self::assertSame(0, preg_match($regex, '/posts/123'));
    }

    #[Test]
    public function get_or_compute_returns_same_result_on_repeated_call(): void
    {
        $first = $this->cache->getOrCompute('/users/{id}');
        $second = $this->cache->getOrCompute('/users/{id}');

        self::assertSame($first, $second);
    }

    #[Test]
    public function get_or_compute_different_templates_return_different_regex(): void
    {
        $users = $this->cache->getOrCompute('/users/{id}');
        $posts = $this->cache->getOrCompute('/posts/{postId}');

        self::assertNotSame($users, $posts);
    }

    #[Test]
    public function clear_resets_cache(): void
    {
        $before = $this->cache->getOrCompute('/users/{id}');

        $this->cache->clear();

        $after = $this->cache->getOrCompute('/users/{id}');

        self::assertSame($before, $after);
        self::assertSame(1, $this->cacheSize());
    }

    #[Test]
    public function dot_in_fixed_part_does_not_match_any_char(): void
    {
        $regex = $this->cache->getOrCompute('/v1.0/users');

        self::assertSame(1, preg_match($regex, '/v1.0/users'));
        self::assertSame(0, preg_match($regex, '/v1X0/users'));
        self::assertSame(0, preg_match($regex, '/v1_0/users'));
    }

    #[Test]
    public function plus_in_fixed_part_is_literal_not_quantifier(): void
    {
        $regex = $this->cache->getOrCompute('/search+advanced');

        self::assertSame(1, preg_match($regex, '/search+advanced'));
        self::assertSame(0, preg_match($regex, '/searchadvanced'));
    }

    #[Test]
    public function parenthesis_in_fixed_part_is_literal_not_group(): void
    {
        $regex = $this->cache->getOrCompute('/query(a)');

        self::assertSame(1, preg_match($regex, '/query(a)'));
        self::assertSame(0, preg_match($regex, '/querya'));
    }

    #[Test]
    public function hash_in_fixed_part_does_not_break_delimiter(): void
    {
        $regex = $this->cache->getOrCompute('/path#fragment');

        self::assertSame(1, preg_match($regex, '/path#fragment'));
        self::assertSame(0, preg_match($regex, '/pathXfragment'));
    }

    #[Test]
    public function placeholder_with_suffix_literal_matches_captured_name(): void
    {
        $regex = $this->cache->getOrCompute('/files/{name}.json');

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
        $regex = $this->cache->getOrCompute('/users/{id}');

        $matches = [];
        preg_match($regex, '/users/42', $matches);

        self::assertSame('42', $matches['id']);
        self::assertSame(0, preg_match($regex, '/users/42/details'));
    }

    #[Test]
    public function level2_reserved_expansion_matches_value_with_slash(): void
    {
        $regex = $this->cache->getOrCompute('/assets/{+path}');

        $matches = [];
        preg_match($regex, '/assets/img/logo.png', $matches);

        self::assertSame(1, preg_match($regex, '/assets/img/logo.png'));
        self::assertSame('img/logo.png', $matches['path']);
    }

    #[Test]
    public function level1_placeholder_rejects_value_with_slash(): void
    {
        $regex = $this->cache->getOrCompute('/assets/{path}');

        self::assertSame(0, preg_match($regex, '/assets/img/logo.png'));
        self::assertSame(1, preg_match($regex, '/assets/img'));
    }

    #[Test]
    public function level2_reserved_expansion_rejects_value_with_query(): void
    {
        $regex = $this->cache->getOrCompute('/assets/{+path}');

        self::assertSame(0, preg_match($regex, '/assets/img?version=1'));
        self::assertSame(0, preg_match($regex, '/assets/img#section'));
    }

    #[Test]
    public function multiple_level1_placeholders_capture_independently(): void
    {
        $regex = $this->cache->getOrCompute('/users/{userId}/posts/{postId}');

        $matches = [];
        preg_match($regex, '/users/42/posts/7', $matches);

        self::assertSame('42', $matches['userId']);
        self::assertSame('7', $matches['postId']);
    }

    #[Test]
    public function mixed_level1_and_level2_placeholders(): void
    {
        $regex = $this->cache->getOrCompute('/{lang}/assets/{+path}');

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

        $this->cache->getOrCompute('/users/{123invalid}');
    }

    #[Test]
    public function invalid_name_starting_with_dollar_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->getOrCompute('/users/{$var}');
    }

    #[Test]
    public function unsupported_fragment_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported path template operator');

        $this->cache->getOrCompute('/path/{#frag}');
    }

    #[Test]
    public function unsupported_query_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported path template operator');

        $this->cache->getOrCompute('/path/{?query}');
    }

    #[Test]
    public function unsupported_semicolon_operator_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->getOrCompute('/path/{;param}');
    }

    #[Test]
    public function empty_placeholder_name_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->cache->getOrCompute('/users/{}');
    }

    #[Test]
    public function constructor_rejects_max_size_below_one(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max size must be at least 1, got 0');

        new PathRegexCache(0);
    }

    #[Test]
    public function constructor_accepts_max_size_of_one(): void
    {
        $cache = new PathRegexCache(maxSize: 1);

        $cache->getOrCompute('/a/{x}');

        // Second insert evicts the first; cache stays at 1.
        $cache->getOrCompute('/b/{x}');

        self::assertSame(1, $this->cacheSize($cache));
        self::assertSame(['/b/{x}'], $this->cacheKeys($cache));
    }

    #[Test]
    public function constructor_rejects_negative_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new PathRegexCache(-5);
    }

    #[Test]
    public function eviction_keeps_cache_at_max_size_after_overflow(): void
    {
        $cache = new PathRegexCache(maxSize: 3);

        $cache->getOrCompute('/a/{x}');
        $cache->getOrCompute('/b/{x}');
        $cache->getOrCompute('/c/{x}');
        $cache->getOrCompute('/d/{x}');

        self::assertSame(3, $this->cacheSize($cache));
    }

    #[Test]
    public function eviction_removes_least_recently_used_entry(): void
    {
        $cache = new PathRegexCache(maxSize: 3);

        $cache->getOrCompute('/a/{x}');
        $cache->getOrCompute('/b/{x}');
        $cache->getOrCompute('/c/{x}');
        // Touch '/a/{x}' so '/b/{x}' becomes the LRU victim.
        $cache->getOrCompute('/a/{x}');
        $cache->getOrCompute('/d/{x}');

        $keys = $this->cacheKeys($cache);

        self::assertContains('/a/{x}', $keys);
        self::assertContains('/c/{x}', $keys);
        self::assertContains('/d/{x}', $keys);
        self::assertNotContains('/b/{x}', $keys);
    }

    #[Test]
    public function default_capacity_holds_256_entries_and_evicts_on_257(): void
    {
        for ($i = 0; $i < 256; ++$i) {
            $this->cache->getOrCompute(sprintf('/r%d/{id}', $i));
        }

        self::assertSame(256, $this->cacheSize());

        $this->cache->getOrCompute('/overflow/{id}');

        self::assertSame(256, $this->cacheSize());
        self::assertNotContains('/r0/{id}', $this->cacheKeys());
        self::assertContains('/overflow/{id}', $this->cacheKeys());
    }

    #[Test]
    public function touch_promotes_entry_to_most_recently_used(): void
    {
        $cache = new PathRegexCache(maxSize: 2);

        $cache->getOrCompute('/a/{x}');
        $cache->getOrCompute('/b/{x}');
        // Access '/a/{x}' — '/b/{x}' is now LRU.
        $cache->getOrCompute('/a/{x}');
        $cache->getOrCompute('/c/{x}');

        $keys = $this->cacheKeys($cache);

        self::assertContains('/a/{x}', $keys);
        self::assertContains('/c/{x}', $keys);
        self::assertNotContains('/b/{x}', $keys);
    }

    #[Test]
    public function clear_empties_cache_and_allows_reuse(): void
    {
        $this->cache->getOrCompute('/users/{id}');
        $this->cache->getOrCompute('/posts/{id}');

        self::assertSame(2, $this->cacheSize());

        $this->cache->clear();

        self::assertSame(0, $this->cacheSize());

        $reused = $this->cache->getOrCompute('/users/{id}');

        self::assertSame(1, preg_match($reused, '/users/9'));
    }

    #[Test]
    public function custom_max_size_respected(): void
    {
        $cache = new PathRegexCache(maxSize: 1);

        $cache->getOrCompute('/a/{x}');
        $cache->getOrCompute('/b/{x}');

        self::assertSame(1, $this->cacheSize($cache));
        self::assertSame(['/b/{x}'], $this->cacheKeys($cache));
    }

    /**
     * P-008: RFC 6570 §2.3 permits the dot inside a variable name, so
     * `/users/{user.id}` must compile without throwing.
     */
    #[Test]
    public function accepts_dot_in_path_parameter_name(): void
    {
        $regex = $this->cache->getOrCompute('/users/{user.id}');

        self::assertSame(1, preg_match($regex, '/users/42'));
        self::assertSame(0, preg_match($regex, '/users/42/details'));
    }

    /**
     * P-008: The captured value is accessible via a named group. PCRE forbids
     * the dot in subpattern names, so the group name is the varname with dots
     * replaced by underscores.
     */
    #[Test]
    public function extracts_dot_dotted_parameter_value(): void
    {
        $regex = $this->cache->getOrCompute('/users/{user.id}');

        $matches = [];
        preg_match($regex, '/users/42', $matches);

        self::assertSame('42', $matches['user_id']);
    }

    /**
     * P-008: Dotted varnames must also work with the RFC 6570 Level 2
     * reserved-expansion operator `{+name}`.
     */
    #[Test]
    public function accepts_dot_in_reserved_expansion_varname(): void
    {
        $regex = $this->cache->getOrCompute('/assets/{+asset.id}');

        $matches = [];
        preg_match($regex, '/assets/img/logo.png', $matches);

        self::assertSame(1, preg_match($regex, '/assets/img/logo.png'));
        self::assertSame('img/logo.png', $matches['asset_id']);
    }

    /**
     * P-008: Multiple dotted varnames in the same template must capture
     * independently into distinct underscore-named groups.
     */
    #[Test]
    public function multiple_dot_dotted_placeholders_capture_independently(): void
    {
        $regex = $this->cache->getOrCompute('/{api.version}/users/{user.id}');

        $matches = [];
        preg_match($regex, '/v1/users/42', $matches);

        self::assertSame('v1', $matches['api_version']);
        self::assertSame('42', $matches['user_id']);
    }

    /**
     * @return int<0, max>
     */
    private function cacheSize(?PathRegexCache $cache = null): int
    {
        return count($this->cacheArray($cache ?? $this->cache));
    }

    /**
     * @return list<string>
     */
    private function cacheKeys(?PathRegexCache $cache = null): array
    {
        /** @var array<string, string> $data */
        $data = $this->cacheArray($cache ?? $this->cache);

        return array_keys($data);
    }

    /**
     * @return array<string, string>
     */
    private function cacheArray(PathRegexCache $cache): array
    {
        $reflection = new ReflectionClass($cache);
        $property = $reflection->getProperty('cache');

        /** @var array<string, string> $value */
        $value = $property->getValue($cache);

        return $value;
    }
}
