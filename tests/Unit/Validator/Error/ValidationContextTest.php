<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error;

use Duyler\OpenApi\Validator\Error\BreadcrumbManager;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationContextTest extends TestCase
{
    #[Test]
    public function create_context(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $this->assertSame($pool, $context->pool);
        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function enter_and_leave_breadcrumb(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $context->enterBreadcrumb('users');

        $this->assertSame('/users', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();

        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function enter_breadcrumb_index(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $context->enterBreadcrumbIndex(0);

        $this->assertSame('/0', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function chain_breadcrumbs_with_push_pop(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $context->enterBreadcrumb('users');
        $this->assertSame('/users', $context->breadcrumbs->currentPath());

        $context->enterBreadcrumbIndex(0);
        $this->assertSame('/users/0', $context->breadcrumbs->currentPath());

        $context->enterBreadcrumb('name');
        $this->assertSame('/users/0/name', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $this->assertSame('/users/0', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $this->assertSame('/users', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function maintain_pool_across_mutations(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $this->assertSame($pool, $context->pool);

        $context->enterBreadcrumb('users');

        $this->assertSame($pool, $context->pool);
    }

    #[Test]
    public function create_context_with_default_formatter(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $this->assertInstanceOf(SimpleFormatter::class, $context->errorFormatter);
    }

    #[Test]
    public function maintain_formatter_across_mutations(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: $formatter,
        );

        $context->enterBreadcrumb('users');

        $this->assertSame($formatter, $context->errorFormatter);
    }

    #[Test]
    public function use_custom_formatter(): void
    {
        $pool = new ValidatorPool();
        $formatter = new JsonFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: $formatter,
        );

        $this->assertSame($formatter, $context->errorFormatter);
    }

    #[Test]
    public function maintain_formatter_with_breadcrumb_index(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: $formatter,
        );

        $context->enterBreadcrumbIndex(0);

        $this->assertSame($formatter, $context->errorFormatter);
    }

    #[Test]
    public function depth_increment_and_decrement(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $this->assertSame(0, $context->depth());

        $context->incrementDepth();
        $this->assertSame(1, $context->depth());

        $context->incrementDepth();
        $this->assertSame(2, $context->depth());

        $context->decrementDepth();
        $this->assertSame(1, $context->depth());

        $context->decrementDepth();
        $this->assertSame(0, $context->depth());
    }

    #[Test]
    public function breadcrumb_restored_after_pop(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create(pool: $pool);

        $context->enterBreadcrumb('a');
        $context->enterBreadcrumb('b');
        $context->enterBreadcrumb('c');

        $this->assertSame('/a/b/c', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $context->leaveBreadcrumb();

        $this->assertSame('/a', $context->breadcrumbs->currentPath());
    }
}
