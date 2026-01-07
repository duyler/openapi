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
    ) {}

    public static function create(ValidatorPool $pool): self
    {
        return new self(
            breadcrumbs: BreadcrumbManager::create(),
            pool: $pool,
            errorFormatter: new SimpleFormatter(),
        );
    }

    public function withBreadcrumb(string $segment): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->push($segment),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
        );
    }

    public function withBreadcrumbIndex(int $index): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->pushIndex($index),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
        );
    }

    public function withoutBreadcrumb(): self
    {
        return new self(
            breadcrumbs: $this->breadcrumbs->pop(),
            pool: $this->pool,
            errorFormatter: $this->errorFormatter,
        );
    }
}
