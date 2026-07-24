<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use function count;

final readonly class Breadcrumb
{
    /**
     * @param array<int, string> $segments
     */
    public function __construct(
        private readonly array $segments = [],
    ) {}

    public function append(string $segment): self
    {
        $segments = $this->segments;
        $segments[] = $segment;

        return new self($segments);
    }

    public function appendIndex(int $index): self
    {
        return $this->append((string) $index);
    }

    public function toString(): string
    {
        return '/' . implode('/', $this->segments);
    }

    public function current(): ?string
    {
        $lastIndex = array_key_last($this->segments);

        if (null === $lastIndex) {
            return null;
        }

        return $this->segments[$lastIndex];
    }

    public function depth(): int
    {
        return count($this->segments);
    }

    /**
     * @return array<int, string>
     */
    public function segments(): array
    {
        return $this->segments;
    }
}
