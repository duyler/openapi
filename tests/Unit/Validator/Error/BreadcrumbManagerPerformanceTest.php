<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error;

use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BreadcrumbManagerPerformanceTest extends TestCase
{
    #[Test]
    public function adding_100_breadcrumbs_maintains_immutability(): void
    {
        $manager = BreadcrumbManager::create();

        for ($i = 0; $i < 100; ++$i) {
            $manager = $manager->push('segment_' . $i);
        }

        $this->assertSame(100, substr_count($manager->currentPath(), '/'));
    }

    #[Test]
    public function adding_100_breadcrumbs_produces_correct_path(): void
    {
        $manager = BreadcrumbManager::create();

        for ($i = 0; $i < 100; ++$i) {
            $manager = $manager->push('seg' . $i);
        }

        $path = $manager->currentPath();
        $this->assertTrue(str_starts_with($path, '/seg0/seg1/seg2'));
        $this->assertTrue(str_ends_with($path, '/seg99'));
    }

    #[Test]
    public function original_manager_remains_unchanged_after_push(): void
    {
        $original = BreadcrumbManager::create()->push('root');

        $mutated = $original->push('child');

        $this->assertSame('/root', $original->currentPath());
        $this->assertSame('/root/child', $mutated->currentPath());
    }

    #[Test]
    public function original_manager_remains_unchanged_after_pop(): void
    {
        $original = BreadcrumbManager::create()->push('root')->push('child');

        $popped = $original->pop();

        $this->assertSame('/root/child', $original->currentPath());
        $this->assertSame('/root', $popped->currentPath());
    }

    #[Test]
    public function push_index_maintains_immutability(): void
    {
        $original = BreadcrumbManager::create()->push('items');

        $withIndex = $original->pushIndex(42);

        $this->assertSame('/items', $original->currentPath());
        $this->assertSame('/items/42', $withIndex->currentPath());
    }
}
