<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Internal;

use Duyler\OpenApi\Validator\Internal\LruCache;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(LruCache::class)]
final class LruCacheTest extends TestCase
{
    #[Test]
    public function get_returns_null_for_missing_key(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(2);

        self::assertNull($cache->get('missing'));
    }

    #[Test]
    public function set_stores_value_retrievable_via_get(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(2);
        $value = new stdClass();

        $cache->set('a', $value);

        self::assertSame($value, $cache->get('a'));
    }

    #[Test]
    public function set_overwrites_existing_value(): void
    {
        /** @var LruCache<string, stdClass> $cache */
        $cache = new LruCache(2);
        $first = new stdClass();
        $second = new stdClass();

        $cache->set('a', $first);
        $cache->set('a', $second);

        self::assertSame($second, $cache->get('a'));
    }

    #[Test]
    public function set_evicts_least_recently_used_when_capacity_exceeded(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(2);
        $valueB = new stdClass();
        $valueC = new stdClass();

        $cache->set('a', new stdClass());
        $cache->set('b', $valueB);
        $cache->set('c', $valueC);

        self::assertNull($cache->get('a'));
        self::assertSame($valueB, $cache->get('b'));
        self::assertSame($valueC, $cache->get('c'));
    }

    #[Test]
    public function set_does_not_evict_when_at_capacity(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(3);
        $valueA = new stdClass();
        $valueB = new stdClass();
        $valueC = new stdClass();

        $cache->set('a', $valueA);
        $cache->set('b', $valueB);
        $cache->set('c', $valueC);

        self::assertSame($valueA, $cache->get('a'));
        self::assertSame($valueB, $cache->get('b'));
        self::assertSame($valueC, $cache->get('c'));
    }

    #[Test]
    public function get_promotes_entry_to_most_recently_used(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(2);
        $valueA = new stdClass();
        $valueC = new stdClass();

        $cache->set('a', $valueA);
        $cache->set('b', new stdClass());
        $cache->get('a');
        $cache->set('c', $valueC);

        self::assertSame($valueA, $cache->get('a'));
        self::assertNull($cache->get('b'));
        self::assertSame($valueC, $cache->get('c'));
    }

    #[Test]
    public function touch_promotes_entry_without_changing_value(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(2);
        $valueA = new stdClass();
        $valueC = new stdClass();

        $cache->set('a', $valueA);
        $cache->set('b', new stdClass());
        $cache->touch('a');
        $cache->set('c', $valueC);

        self::assertSame($valueA, $cache->get('a'));
        self::assertNull($cache->get('b'));
        self::assertSame($valueC, $cache->get('c'));
    }

    #[Test]
    public function touch_on_missing_key_is_a_noop(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(1);
        $valueZ = new stdClass();

        $cache->touch('ghost');
        $cache->set('y', new stdClass());
        $cache->set('z', $valueZ);

        self::assertNull($cache->get('ghost'));
        self::assertNull($cache->get('y'));
        self::assertSame($valueZ, $cache->get('z'));
    }

    #[Test]
    public function single_entry_cache_evicts_previous_entry(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(1);
        $valueB = new stdClass();

        $cache->set('a', new stdClass());
        $cache->set('b', $valueB);

        self::assertNull($cache->get('a'));
        self::assertSame($valueB, $cache->get('b'));
    }

    #[Test]
    public function evicted_key_can_be_reinserted(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(1);
        $reinserted = new stdClass();

        $cache->set('a', new stdClass());
        $cache->set('b', new stdClass());
        $cache->set('a', $reinserted);

        self::assertSame($reinserted, $cache->get('a'));
        self::assertNull($cache->get('b'));
    }

    #[Test]
    public function clear_removes_all_entries(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(3);
        $value = new stdClass();

        $cache->set('a', new stdClass());
        $cache->set('b', new stdClass());
        $cache->clear();

        self::assertNull($cache->get('a'));
        self::assertNull($cache->get('b'));

        $cache->set('c', $value);

        self::assertSame($value, $cache->get('c'));
    }

    #[Test]
    public function clear_on_empty_cache_is_noop(): void
    {
        /** @var LruCache<string, object> $cache */
        $cache = new LruCache(2);

        $cache->clear();

        self::assertNull($cache->get('a'));
    }

    #[Test]
    public function scalar_values_are_stored_as_is(): void
    {
        /** @var LruCache<string, int> $cache */
        $cache = new LruCache(2);

        $cache->set('a', 42);

        self::assertSame(42, $cache->get('a'));
    }

    #[Test]
    public function constructor_throws_for_zero_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max size must be at least 1, got 0');

        /** @var LruCache<string, object> */
        new LruCache(0);
    }

    #[Test]
    public function constructor_throws_for_negative_max_size(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Max size must be at least 1, got -5');

        /** @var LruCache<string, object> */
        new LruCache(-5);
    }
}
