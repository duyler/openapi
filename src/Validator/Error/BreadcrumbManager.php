<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

/**
 * Immutable manager for tracking breadcrumb during validation
 *
 * Maintains a stack of path segments and provides methods to push/pop segments.
 * Each operation returns a new instance to maintain immutability.
 */
final readonly class BreadcrumbManager
{
    /**
     * @param array<int, string> $stack
     */
    private function __construct(
        private readonly array $stack = [],
    ) {}

    public static function create(): self
    {
        return new self([]);
    }

    public function push(string $segment): self
    {
        return new self([...$this->stack, $segment]);
    }

    public function pushIndex(int $index): self
    {
        return $this->push((string) $index);
    }

    public function pop(): self
    {
        $newStack = $this->stack;
        array_pop($newStack);

        return new self($newStack);
    }

    public function toBreadcrumb(): Breadcrumb
    {
        return new Breadcrumb($this->stack);
    }

    public function currentPath(): string
    {
        return $this->toBreadcrumb()->toString();
    }
}
