<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Schema\JsonEquals;

use function array_intersect;
use function array_merge;
use function array_unique;
use function array_uintersect;
use function array_values;
use function count;
use function is_array;

/** @internal */
final readonly class ScalarSiblingMerger implements SiblingMergerStrategy
{
    /**
     * @return array{
     *     format: ?string,
     *     type: string|list<string>|null,
     *     nullable: bool,
     *     const: string|int|float|bool|array|null,
     *     hasConst: bool,
     *     multipleOf: ?float,
     *     pattern: ?string,
     *     enum: ?list<mixed>,
     * }
     */
    public function merge(SiblingMergeContext $context): array
    {
        $resolved = $context->resolved;
        $sibling = $context->sibling;

        return [
            'format' => $this->mergeFormat($resolved->format, $sibling->format),
            'type' => $this->mergeType($resolved->type, $sibling->type),
            'nullable' => $sibling->nullable && $resolved->nullable,
            'const' => $sibling->hasConst ? $sibling->const : $resolved->const,
            'hasConst' => $sibling->hasConst || $resolved->hasConst,
            'multipleOf' => $this->mergeNullableIdentical($resolved->multipleOf, $sibling->multipleOf),
            'pattern' => $this->mergeNullableIdentical($resolved->pattern, $sibling->pattern),
            'enum' => $this->mergeEnum($resolved->enum, $sibling->enum),
        ];
    }

    public function mergeFormat(?string $resolved, ?string $sibling): ?string
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        return $resolved === $sibling ? $resolved : null;
    }

    /**
     * @param string|list<string>|null $resolved
     * @param string|list<string>|null $sibling
     *
     * @return string|list<string>|null
     */
    public function mergeType(string|array|null $resolved, string|array|null $sibling): string|array|null
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        $resolvedSet = is_array($resolved) ? $resolved : [$resolved];
        $siblingSet = is_array($sibling) ? $sibling : [$sibling];
        $intersection = array_values(array_intersect($resolvedSet, $siblingSet));

        if ([] === $intersection) {
            return null;
        }

        return 1 === count($intersection) ? $intersection[0] : $intersection;
    }

    /**
     * @param ?list<mixed> $resolved
     * @param ?list<mixed> $sibling
     *
     * @return ?list<mixed>
     */
    public function mergeEnum(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_values(array_uintersect(
            $resolved,
            $sibling,
            static fn(mixed $a, mixed $b): int => JsonEquals::equals($a, $b)
                ? 0
                : ($a <=> $b),
        ));
    }

    /**
     * @template T
     *
     * @param T|null $resolved
     * @param T|null $sibling
     *
     * @return T|null
     */
    public function mergeNullableIdentical(mixed $resolved, mixed $sibling): mixed
    {
        if (null === $resolved) {
            return $sibling;
        }

        if (null === $sibling) {
            return $resolved;
        }

        return null;
    }

    /**
     * @param ?list<string> $resolved
     * @param ?list<string> $sibling
     *
     * @return ?list<string>
     */
    public function mergeStringList(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_values(array_unique(array_merge($resolved, $sibling)));
    }

    /**
     * @param ?array<string, mixed> $resolved
     * @param ?array<string, mixed> $sibling
     *
     * @return ?array<string, mixed>
     */
    public function mergeMixedMap(?array $resolved, ?array $sibling): ?array
    {
        if (null === $sibling) {
            return $resolved;
        }

        if (null === $resolved) {
            return $sibling;
        }

        return array_merge($resolved, $sibling);
    }

    /** @return list<Schema> */
    public function collectScalarFieldAdditions(Schema $resolved, Schema $sibling): array
    {
        $additions = [];

        if (null !== $resolved->multipleOf && null !== $sibling->multipleOf) {
            $additions[] = new Schema(multipleOf: $resolved->multipleOf);
            $additions[] = new Schema(multipleOf: $sibling->multipleOf);
        }

        if (null !== $resolved->pattern && null !== $sibling->pattern) {
            $additions[] = new Schema(pattern: $resolved->pattern);
            $additions[] = new Schema(pattern: $sibling->pattern);
        }

        return $additions;
    }
}
