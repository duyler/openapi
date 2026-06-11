<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;

final readonly class ValidationContext
{
    public function __construct(
        public readonly BreadcrumbManager $breadcrumbs,
        public readonly ValidatorPool $pool,
        public readonly ErrorFormatterInterface $errorFormatter = new SimpleFormatter(),
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        public readonly int $depth = 0,
        public readonly ?ValidatorMode $mode = null,
    ) {}

    public static function create(
        ValidatorPool $pool,
        bool $nullableAsType = true,
        EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        ?ValidatorMode $mode = null,
    ): self {
        return new self(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
            nullableAsType: $nullableAsType,
            emptyArrayStrategy: $emptyArrayStrategy,
            mode: $mode,
        );
    }

    public function withBreadcrumb(string $segment): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->push($segment),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            depth: $this->depth,
            mode: $this->mode,
        );
    }

    public function withBreadcrumbIndex(int $index): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->pushIndex($index),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            depth: $this->depth,
            mode: $this->mode,
        );
    }

    public function withIncrementedDepth(): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs,
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
            depth: $this->depth + 1,
            mode: $this->mode,
        );
    }
}
