<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request\Internal;

/** @internal */
final readonly class MultipartPartNode
{
    /**
     * @param array<string, string> $headers
     * @param list<self> $children
     */
    private function __construct(
        public readonly array $headers,
        public readonly string $content,
        public readonly array $children,
        public readonly string|bool|int|float|array|null $value,
    ) {}

    /**
     * @param array<string, string> $headers
     */
    public static function leaf(array $headers, string $content, string|bool|int|float|array|null $value): self
    {
        return new self(headers: $headers, content: $content, children: [], value: $value);
    }

    /**
     * The tree is heterogeneous: a nested node parsed as a positional or
     * plain level keeps its part nodes in $children and mirrors their
     * values in $value, while a nested node parsed as a named level keeps
     * $children empty and carries the name => value map only in $value.
     *
     * @param array<string, string> $headers
     * @param list<self> $children
     */
    public static function nested(
        array $headers,
        string $content,
        array $children,
        string|bool|int|float|array|null $value,
    ): self {
        return new self(headers: $headers, content: $content, children: $children, value: $value);
    }
}
