<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error;

use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BreadcrumbManagerPerformanceTest extends TestCase
{
    #[Test]
    public function adding_100_breadcrumbs_mutable_in_place(): void
    {
        $manager = BreadcrumbManager::create();

        for ($i = 0; $i < 100; ++$i) {
            $manager->push('segment_' . $i);
        }

        $this->assertSame(100, substr_count($manager->currentPath(), '/'));
    }

    #[Test]
    public function adding_100_breadcrumbs_produces_correct_path(): void
    {
        $manager = BreadcrumbManager::create();

        for ($i = 0; $i < 100; ++$i) {
            $manager->push('seg' . $i);
        }

        $path = $manager->currentPath();
        $this->assertTrue(str_starts_with($path, '/seg0/seg1/seg2'));
        $this->assertTrue(str_ends_with($path, '/seg99'));
    }

    #[Test]
    public function push_pop_maintains_correct_state(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('root');
        $manager->push('child');

        $this->assertSame('/root/child', $manager->currentPath());

        $manager->pop();

        $this->assertSame('/root', $manager->currentPath());

        $manager->push('other');

        $this->assertSame('/root/other', $manager->currentPath());
    }

    #[Test]
    public function push_index_in_place(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('items');
        $manager->pushIndex(42);

        $this->assertSame('/items/42', $manager->currentPath());

        $manager->pop();

        $this->assertSame('/items', $manager->currentPath());
    }

    #[Test]
    public function no_allocation_on_push(): void
    {
        $manager = BreadcrumbManager::create();
        $original = $manager;

        $manager->push('segment');

        $this->assertSame($original, $manager);
        $this->assertSame('/segment', $manager->currentPath());
    }
}
