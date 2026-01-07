<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BreadcrumbManagerTest extends TestCase
{
    #[Test]
    public function create_empty_manager(): void
    {
        $manager = BreadcrumbManager::create();

        $this->assertSame('/', $manager->currentPath());
        $breadcrumb = $manager->toBreadcrumb();
        $this->assertSame('/', $breadcrumb->toString());
    }

    #[Test]
    public function push_and_pop_segments(): void
    {
        $manager = BreadcrumbManager::create();
        $manager2 = $manager->push('users');
        $manager3 = $manager2->push('name');

        $this->assertSame('/', $manager->currentPath());
        $this->assertSame('/users', $manager2->currentPath());
        $this->assertSame('/users/name', $manager3->currentPath());

        $manager4 = $manager3->pop();
        $this->assertSame('/users', $manager4->currentPath());
    }

    #[Test]
    public function push_index(): void
    {
        $manager = BreadcrumbManager::create();
        $manager2 = $manager->push('users')->pushIndex(0);

        $this->assertSame('/users/0', $manager2->currentPath());
    }

    #[Test]
    public function track_nested_path(): void
    {
        $manager = BreadcrumbManager::create();
        $manager = $manager->push('data')->pushIndex(0)->push('items')->pushIndex(5);

        $this->assertSame('/data/0/items/5', $manager->currentPath());
    }

    #[Test]
    public function create_breadcrumb_from_stack(): void
    {
        $manager = BreadcrumbManager::create();
        $manager = $manager->push('users')->pushIndex(0)->push('name');

        $breadcrumb = $manager->toBreadcrumb();

        $this->assertSame('/users/0/name', $breadcrumb->toString());
        $this->assertSame(['users', '0', 'name'], $breadcrumb->segments());
    }

    #[Test]
    public function maintain_immutability(): void
    {
        $manager = BreadcrumbManager::create();
        $manager2 = $manager->push('users');

        $this->assertNotSame($manager, $manager2);
        $this->assertSame('/', $manager->currentPath());
        $this->assertSame('/users', $manager2->currentPath());
    }

    #[Test]
    public function pop_from_empty_manager(): void
    {
        $manager = BreadcrumbManager::create();
        $manager2 = $manager->pop();

        $this->assertSame('/', $manager2->currentPath());
    }
}
