<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error;

use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
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
        $manager->push('users');
        $manager->push('name');

        $this->assertSame('/users/name', $manager->currentPath());

        $manager->pop();

        $this->assertSame('/users', $manager->currentPath());
    }

    #[Test]
    public function push_index(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('users');
        $manager->pushIndex(0);

        $this->assertSame('/users/0', $manager->currentPath());
    }

    #[Test]
    public function track_nested_path(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('data');
        $manager->pushIndex(0);
        $manager->push('items');
        $manager->pushIndex(5);

        $this->assertSame('/data/0/items/5', $manager->currentPath());
    }

    #[Test]
    public function create_breadcrumb_from_stack(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('users');
        $manager->pushIndex(0);
        $manager->push('name');

        $breadcrumb = $manager->toBreadcrumb();

        $this->assertSame('/users/0/name', $breadcrumb->toString());
        $this->assertSame(['users', '0', 'name'], $breadcrumb->segments());
    }

    #[Test]
    public function mutate_in_place_on_push(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('users');

        $this->assertSame('/users', $manager->currentPath());
    }

    #[Test]
    public function pop_from_empty_manager(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->pop();

        $this->assertSame('/', $manager->currentPath());
    }

    #[Test]
    public function push_pop_round_trip_restores_state(): void
    {
        $manager = BreadcrumbManager::create();
        $manager->push('root');

        $manager->push('child');
        $manager->pop();

        $this->assertSame('/root', $manager->currentPath());
    }
}
