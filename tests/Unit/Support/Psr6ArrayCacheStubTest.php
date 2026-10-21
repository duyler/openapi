<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Support;

use Duyler\OpenApi\Test\Support\Psr6ArrayCacheStub;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Psr6ArrayCacheStubTest extends TestCase
{
    #[Test]
    public function repeated_miss_reads_of_unsaved_key_count_once(): void
    {
        $pool = new Psr6ArrayCacheStub();

        $pool->getItem('openapi_spec_content_a');
        $pool->getItem('openapi_spec_content_a');

        self::assertSame(1, $pool->missCount());
    }

    #[Test]
    public function item_returned_on_miss_read_is_never_a_hit(): void
    {
        $pool = new Psr6ArrayCacheStub();

        $pool->getItem('openapi_spec_content_a');
        $item = $pool->getItem('openapi_spec_content_a');

        self::assertFalse($item->isHit());
        self::assertNull($item->get());
        self::assertFalse($pool->hasItem('openapi_spec_content_a'));
    }

    #[Test]
    public function confinement_miss_is_excluded_from_miss_count_and_saved_keys(): void
    {
        $pool = new Psr6ArrayCacheStub();

        $item = $pool->getItem('openapi_spec_content_a_confinement');
        $pool->save($item->set('verdict'));

        self::assertSame(0, $pool->missCount());
        self::assertSame(1, $pool->writeCount);
        self::assertSame(0, $pool->saveCount());
        self::assertSame(['openapi_spec_content_a_confinement'], $pool->confinementKeys());
    }

    #[Test]
    public function repeated_save_of_same_key_keeps_last_value_with_unique_save_count(): void
    {
        $pool = new Psr6ArrayCacheStub();

        $pool->save($pool->getItem('openapi_spec_content_a')->set('first'));
        $pool->save($pool->getItem('openapi_spec_content_a')->set('second'));

        self::assertSame('second', $pool->value('openapi_spec_content_a'));
        self::assertSame(1, $pool->saveCount());
        self::assertSame(2, $pool->writeCount);
    }
}
