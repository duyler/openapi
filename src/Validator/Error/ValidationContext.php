<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;

final class ValidationContext
{
    public const int MAX_DEPTH = 64;

    /** @var array<string, true> */
    private array $evaluatedPropertyNames = [];

    /** @var array<int, true> */
    private array $evaluatedItemIndices = [];

    /**
     * @deprecated since 1.x, will be removed in 2.0. Use {@see fromInit()} instead.
     */
    public function __construct(
        public readonly BreadcrumbManager $breadcrumbs,
        public readonly ValidatorPool $pool,
        public readonly ErrorFormatterInterface $errorFormatter,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private int $depth = 0,
        public readonly ?ValidatorMode $mode = null,
    ) {}

    /**
     * @deprecated since 1.x, will be removed in 2.0. Use {@see fromInit()} instead.
     */
    public static function create(
        ValidatorPool $pool,
        ?ErrorFormatterInterface $errorFormatter = null,
        bool $nullableAsType = true,
        EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        ?ValidatorMode $mode = null,
    ): self {
        return new self(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: $errorFormatter ?? new SimpleFormatter(),
            nullableAsType: $nullableAsType,
            emptyArrayStrategy: $emptyArrayStrategy,
            mode: $mode,
        );
    }

    public static function fromInit(ValidationContextInit $init): self
    {
        return new self(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $init->pool,
            errorFormatter: $init->errorFormatter ?? new SimpleFormatter(),
            nullableAsType: $init->nullableAsType,
            emptyArrayStrategy: $init->emptyArrayStrategy,
            mode: $init->mode,
        );
    }

    public function enterBreadcrumb(string $segment): void
    {
        $this->breadcrumbs->push($segment);
    }

    public function enterBreadcrumbIndex(int $index): void
    {
        $this->breadcrumbs->pushIndex($index);
    }

    public function leaveBreadcrumb(): void
    {
        $this->breadcrumbs->pop();
    }

    public function incrementDepth(): void
    {
        if ($this->depth >= self::MAX_DEPTH) {
            throw new SchemaDepthExceededException(self::MAX_DEPTH);
        }

        ++$this->depth;
    }

    public function decrementDepth(): void
    {
        --$this->depth;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function markPropertyEvaluated(string $name): void
    {
        $this->evaluatedPropertyNames[$name] = true;
    }

    public function markItemEvaluated(int $index): void
    {
        $this->evaluatedItemIndices[$index] = true;
    }

    /** @return list<string> */
    public function evaluatedPropertyNames(): array
    {
        return array_keys($this->evaluatedPropertyNames);
    }

    /** @return list<int> */
    public function evaluatedItemIndices(): array
    {
        return array_keys($this->evaluatedItemIndices);
    }

    public function hasPropertyBeenEvaluated(string $name): bool
    {
        return isset($this->evaluatedPropertyNames[$name]);
    }

    public function hasItemBeenEvaluated(int $index): bool
    {
        return isset($this->evaluatedItemIndices[$index]);
    }

    public function mergeChildAnnotations(self $child): void
    {
        $this->evaluatedPropertyNames += $child->evaluatedPropertyNames;
        $this->evaluatedItemIndices += $child->evaluatedItemIndices;
    }

    public function forkForBranch(): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs,
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            depth: $this->depth,
            mode: $this->mode,
        );
    }
}
