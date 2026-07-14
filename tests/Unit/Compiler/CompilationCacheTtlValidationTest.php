<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\CompilationCache;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(CompilationCache::class)]
final class CompilationCacheTtlValidationTest extends TestCase
{
    private CacheItemPoolInterface $pool;

    #[Override]
    protected function setUp(): void
    {
        $this->pool = $this->createStub(CacheItemPoolInterface::class);
    }

    #[Test]
    public function ttl_zero_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CompilationCache($this->pool, ttl: 0);
    }

    #[Test]
    public function ttl_negative_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new CompilationCache($this->pool, ttl: -1);
    }

    #[Test]
    public function ttl_one_accepted(): void
    {
        $cache = new CompilationCache($this->pool, ttl: 1);

        self::assertInstanceOf(CompilationCache::class, $cache);
    }

    #[Test]
    public function ttl_positive_accepted(): void
    {
        $cache = new CompilationCache($this->pool, ttl: 3600);

        self::assertInstanceOf(CompilationCache::class, $cache);
    }

    #[Test]
    public function default_ttl_accepted(): void
    {
        $cache = new CompilationCache($this->pool);

        self::assertInstanceOf(CompilationCache::class, $cache);
    }

    #[Test]
    public function error_message_contains_actual_value(): void
    {
        $caught = null;

        try {
            new CompilationCache($this->pool, ttl: 0);
        } catch (InvalidArgumentException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertStringContainsString('got 0', $caught->getMessage());
    }
}
