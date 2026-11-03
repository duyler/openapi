<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Dto;

/** @internal */
final readonly class ValidationAnnotations
{
    /**
     * @param array<string, list<array{path: string, value: mixed}>> $annotations
     */
    public function __construct(
        private array $annotations = [],
    ) {}

    public function with(string $keyword, string $path, mixed $value): self
    {
        $annotations = $this->annotations;
        $annotations[$keyword][] = ['path' => $path, 'value' => $value];

        return new self($annotations);
    }

    public function merge(self $other): self
    {
        $annotations = $this->annotations;

        foreach ($other->annotations as $keyword => $records) {
            $annotations[$keyword] = [...($annotations[$keyword] ?? []), ...$records];
        }

        return new self($annotations);
    }

    /**
     * @return array<string, list<array{path: string, value: mixed}>>
     */
    public function toArray(): array
    {
        return $this->annotations;
    }
}
