<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Cache;

use Duyler\OpenApi\Cache\SchemaCache;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(SchemaCache::class)]
final class SchemaCacheTtlValidationTest extends TestCase
{
    private CacheItemPoolInterface $pool;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = $this->createStub(CacheItemPoolInterface::class);
    }

    #[Test]
    public function ttl_zero_throws_via_typed_cache_decorator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('TTL must be a positive integer, got 0.');

        new SchemaCache($this->pool, 0);
    }

    #[Test]
    public function ttl_positive_accepted(): void
    {
        $cache = new SchemaCache($this->pool, 3600);

        self::assertInstanceOf(SchemaCache::class, $cache);
    }
}
