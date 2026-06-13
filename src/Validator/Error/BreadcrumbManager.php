<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use function implode;

final class BreadcrumbManager
{
    /** @var array<int, string> */
    private array $stack = [];

    public static function create(): self
    {
        return new self();
    }

    public function push(string $segment): void
    {
        $this->stack[] = $segment;
    }

    public function pushIndex(int $index): void
    {
        $this->stack[] = (string) $index;
    }

    public function pop(): void
    {
        array_pop($this->stack);
    }

    public function toBreadcrumb(): Breadcrumb
    {
        return new Breadcrumb($this->stack);
    }

    public function currentPath(): string
    {
        if ([] === $this->stack) {
            return '/';
        }

        return '/' . implode('/', $this->stack);
    }
}
