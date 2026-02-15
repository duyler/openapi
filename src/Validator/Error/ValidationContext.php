<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\ValidatorPool;

readonly class ValidationContext
{
    public function __construct(
        public readonly BreadcrumbManager $breadcrumbs,
        public readonly ValidatorPool $pool,
        public readonly ErrorFormatterInterface $errorFormatter = new SimpleFormatter(),
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
    ) {}

    public static function create(
        ValidatorPool $pool,
        bool $nullableAsType = true,
        EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
    ): self {
        return new self(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
            nullableAsType: $nullableAsType,
            emptyArrayStrategy: $emptyArrayStrategy,
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
        );
    }

    public function withoutBreadcrumb(): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->pop(),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
            emptyArrayStrategy: $this->emptyArrayStrategy,
        );
    }
}
