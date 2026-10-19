<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Cache;

use Duyler\OpenApi\Cache\ConfinementVerdict;
use Duyler\OpenApi\Cache\SchemaCache;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use stdClass;

use DateInterval;
use DateTimeInterface;

use function array_key_exists;

final class ConfinementVerdictTest extends TestCase
{
    #[Test]
    public function clean_returns_confinement_verdict_instance(): void
    {
        $verdict = ConfinementVerdict::clean();

        self::assertInstanceOf(ConfinementVerdict::class, $verdict);
    }

    #[Test]
    public function set_confinement_writes_verdict_through_pool(): void
    {
        $pool = new CountingPool();
        $cache = new SchemaCache($pool);

        $cache->setConfinement('k');

        self::assertTrue($pool->hasItem('k'));
        self::assertSame(1, $pool->writeCount);
    }

    #[Test]
    public function get_confinement_returns_verdict_after_set(): void
    {
        $cache = new SchemaCache(new CountingPool());

        $cache->setConfinement('k');
        $verdict = $cache->getConfinement('k');

        self::assertInstanceOf(ConfinementVerdict::class, $verdict);
    }

    #[Test]
    public function get_confinement_returns_null_on_miss(): void
    {
        $cache = new SchemaCache(new CountingPool());

        $verdict = $cache->getConfinement('k');

        self::assertNull($verdict);
    }

    #[Test]
    public function get_confinement_returns_null_on_foreign_type(): void
    {
        $pool = new CountingPool();
        $pool->save($pool->getItem('k')->set(new stdClass()));
        $cache = new SchemaCache($pool);

        $verdict = $cache->getConfinement('k');

        self::assertNull($verdict);
    }
}

final class CountingPool implements CacheItemPoolInterface
{
    public int $readCount = 0;
    public int $writeCount = 0;

    /** @var array<string, mixed> */
    private array $values = [];

    public function getItem(string $key): CacheItemInterface
    {
        ++$this->readCount;

        return new CountingItem($key, $this->values[$key] ?? null, array_key_exists($key, $this->values));
    }

    /** @param list<string> $keys */
    public function getItems(array $keys = []): array
    {
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $this->getItem($k);
        }

        return $out;
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $k) {
            unset($this->values[$k]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        ++$this->writeCount;
        $this->values[$item->getKey()] = $item->get();

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

final class CountingItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value,
        private readonly bool $hit,
    ) {}

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function expiresAt(?DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(int|DateInterval|null $time): static
    {
        return $this;
    }
}
