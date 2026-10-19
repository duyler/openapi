<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Cache;

use Duyler\OpenApi\Cache\ConfinementVerdict;
use Duyler\OpenApi\Cache\SchemaCache;
use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

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
        $pool = new Psr6ArrayCacheStub();
        $cache = new SchemaCache($pool);

        $cache->setConfinement('k');

        self::assertTrue($pool->hasItem('k'));
        self::assertSame(1, $pool->writeCount);
    }

    #[Test]
    public function get_confinement_returns_verdict_after_set(): void
    {
        $cache = new SchemaCache(new Psr6ArrayCacheStub());

        $cache->setConfinement('k');
        $verdict = $cache->getConfinement('k');

        self::assertInstanceOf(ConfinementVerdict::class, $verdict);
    }

    #[Test]
    public function get_confinement_returns_null_on_miss(): void
    {
        $cache = new SchemaCache(new Psr6ArrayCacheStub());

        $verdict = $cache->getConfinement('k');

        self::assertNull($verdict);
    }

    #[Test]
    public function get_confinement_returns_null_on_foreign_type(): void
    {
        $pool = new Psr6ArrayCacheStub();
        $pool->save($pool->getItem('k')->set(new stdClass()));
        $cache = new SchemaCache($pool);

        $verdict = $cache->getConfinement('k');

        self::assertNull($verdict);
    }
}
