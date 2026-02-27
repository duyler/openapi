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
        $context = ValidationContext::create($pool);

        $this->assertSame($pool, $context->pool);
        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function with_breadcrumb(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create($pool);
        $context2 = $context->withBreadcrumb('users');

        $this->assertNotSame($context, $context2);
        $this->assertSame($pool, $context2->pool);
        $this->assertSame('/', $context->breadcrumbs->currentPath());
        $this->assertSame('/users', $context2->breadcrumbs->currentPath());
    }

    #[Test]
    public function with_breadcrumb_index(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create($pool);
        $context2 = $context->withBreadcrumbIndex(0);

        $this->assertSame('/0', $context2->breadcrumbs->currentPath());
    }

    #[Test]
    public function without_breadcrumb(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create($pool);
        $context2 = $context->withBreadcrumb('users');
        $context3 = $context2->withoutBreadcrumb();

        $this->assertSame('/users', $context2->breadcrumbs->currentPath());
        $this->assertSame('/', $context3->breadcrumbs->currentPath());
    }

    #[Test]
    public function chain_breadcrumbs(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create($pool);
        $context2 = $context->withBreadcrumb('users');
        $context3 = $context2->withBreadcrumbIndex(0);
        $context4 = $context3->withBreadcrumb('name');

        $this->assertSame('/', $context->breadcrumbs->currentPath());
        $this->assertSame('/users', $context2->breadcrumbs->currentPath());
        $this->assertSame('/users/0', $context3->breadcrumbs->currentPath());
        $this->assertSame('/users/0/name', $context4->breadcrumbs->currentPath());
    }

    #[Test]
    public function maintain_pool_across_contexts(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create($pool);
        $context2 = $context->withBreadcrumb('users');

        $this->assertSame($pool, $context->pool);
        $this->assertSame($pool, $context2->pool);
        $this->assertSame($context->pool, $context2->pool);
    }

    #[Test]
    public function create_context_with_default_formatter(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::create($pool);

        $this->assertInstanceOf(SimpleFormatter::class, $context->errorFormatter);
    }

    #[Test]
    public function maintain_formatter_across_contexts(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $context = new ValidationContext(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: $formatter,
        );
        $context2 = $context->withBreadcrumb('users');

        $this->assertSame($formatter, $context->errorFormatter);
        $this->assertSame($formatter, $context2->errorFormatter);
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
        $context2 = $context->withBreadcrumbIndex(0);

        $this->assertSame($formatter, $context->errorFormatter);
        $this->assertSame($formatter, $context2->errorFormatter);
    }

    #[Test]
    public function maintain_formatter_without_breadcrumb(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $context = ValidationContext::create($pool)->withBreadcrumb('users');
        $context2 = $context->withoutBreadcrumb();

        $this->assertInstanceOf(SimpleFormatter::class, $context2->errorFormatter);
    }
}
