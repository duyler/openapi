<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class BreadcrumbTest extends TestCase
{
    #[Test]
    public function create_empty_breadcrumb(): void
    {
        $breadcrumb = new Breadcrumb();

        $this->assertSame('/', $breadcrumb->toString());
        $this->assertSame(0, $breadcrumb->depth());
        $this->assertNull($breadcrumb->current());
        $this->assertSame([], $breadcrumb->segments());
    }

    #[Test]
    public function append_segment(): void
    {
        $breadcrumb = new Breadcrumb();
        $newBreadcrumb = $breadcrumb->append('users');

        $this->assertSame('/users', $newBreadcrumb->toString());
        $this->assertSame(1, $newBreadcrumb->depth());
        $this->assertSame('users', $newBreadcrumb->current());
        $this->assertSame(['users'], $newBreadcrumb->segments());

        // Original breadcrumb should be unchanged (immutable)
        $this->assertSame('/', $breadcrumb->toString());
    }

    #[Test]
    public function append_index(): void
    {
        $breadcrumb = new Breadcrumb();
        $newBreadcrumb = $breadcrumb->append('users')->appendIndex(0);

        $this->assertSame('/users/0', $newBreadcrumb->toString());
        $this->assertSame(2, $newBreadcrumb->depth());
        $this->assertSame('0', $newBreadcrumb->current());
        $this->assertSame(['users', '0'], $newBreadcrumb->segments());
    }

    #[Test]
    public function create_new_instance_on_append(): void
    {
        $breadcrumb = new Breadcrumb();
        $breadcrumb2 = $breadcrumb->append('users');
        $breadcrumb3 = $breadcrumb2->append('name');

        $this->assertNotSame($breadcrumb, $breadcrumb2);
        $this->assertNotSame($breadcrumb2, $breadcrumb3);
        $this->assertSame('/', $breadcrumb->toString());
        $this->assertSame('/users', $breadcrumb2->toString());
        $this->assertSame('/users/name', $breadcrumb3->toString());
    }

    #[Test]
    public function calculate_depth(): void
    {
        $breadcrumb = new Breadcrumb();

        $this->assertSame(0, $breadcrumb->depth());

        $breadcrumb = $breadcrumb->append('users')->appendIndex(0)->append('name');
        $this->assertSame(3, $breadcrumb->depth());
    }

    #[Test]
    public function handle_complex_path(): void
    {
        $breadcrumb = new Breadcrumb();
        $breadcrumb = $breadcrumb->append('users')
            ->appendIndex(0)
            ->append('addresses')
            ->appendIndex(1)
            ->append('street');

        $this->assertSame('/users/0/addresses/1/street', $breadcrumb->toString());
        $this->assertSame(5, $breadcrumb->depth());
        $this->assertSame('street', $breadcrumb->current());
    }
}
