<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

/**
 * Immutable manager for tracking breadcrumb during validation
 *
 * Maintains a stack of path segments and provides methods to push/pop segments.
 * Each operation returns a new instance to maintain immutability.
 */
final class BreadcrumbManager
{
    /**
     * @param array<int, string> $stack
     */
    private function __construct(
        private array $stack = [],
    ) {}

    public static function create(): self
    {
        return new self([]);
    }

    public function push(string $segment): self
    {
        $clone = clone $this;
        $clone->stack[] = $segment;

        return $clone;
    }

    public function pushIndex(int $index): self
    {
        return $this->push((string) $index);
    }

    public function pop(): self
    {
        $clone = clone $this;
        array_pop($clone->stack);

        return $clone;
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
