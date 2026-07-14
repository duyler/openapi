<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Cache;

use Duyler\OpenApi\Cache\TypedCacheDecorator;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

#[CoversClass(TypedCacheDecorator::class)]
final class TypedCacheDecoratorTtlValidationTest extends TestCase
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

        new TypedCacheDecorator($this->pool, 0);
    }

    #[Test]
    public function ttl_negative_throws_invalid_argument_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new TypedCacheDecorator($this->pool, -1);
    }

    #[Test]
    public function ttl_one_accepted(): void
    {
        $decorator = new TypedCacheDecorator($this->pool, 1);

        self::assertInstanceOf(TypedCacheDecorator::class, $decorator);
    }

    #[Test]
    public function ttl_default_accepted(): void
    {
        $decorator = new TypedCacheDecorator($this->pool);

        self::assertInstanceOf(TypedCacheDecorator::class, $decorator);
    }

    #[Test]
    public function error_message_contains_actual_value(): void
    {
        $caught = null;

        try {
            new TypedCacheDecorator($this->pool, 0);
        } catch (InvalidArgumentException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertSame('TTL must be a positive integer, got 0.', $caught->getMessage());
    }
}
