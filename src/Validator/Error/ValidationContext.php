<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\ValidatorPool;

/**
 * Immutable context object passed through validation chain
 *
 * Contains breadcrumb manager for tracking path, validator pool
 * for caching validator instances, and error formatter for displaying errors.
 */
readonly class ValidationContext
{
    public function __construct(
        public readonly BreadcrumbManager $breadcrumbs,
        public readonly ValidatorPool $pool,
        public readonly ErrorFormatterInterface $errorFormatter = new SimpleFormatter(),
        public readonly bool $nullableAsType = true,
    ) {}

    public static function create(ValidatorPool $pool, bool $nullableAsType = true): self
    {
        return new self(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
            nullableAsType: $nullableAsType,
        );
    }

    public function withBreadcrumb(string $segment): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->push($segment),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
        );
    }

    public function withBreadcrumbIndex(int $index): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->pushIndex($index),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
        );
    }

    public function withoutBreadcrumb(): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->pop(),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
            nullableAsType: $this->nullableAsType,
        );
    }
}
