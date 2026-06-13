<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;

final class ValidationContext
{
    public const int MAX_DEPTH = 64;

    public function __construct(
        public readonly BreadcrumbManager $breadcrumbs,
        public readonly ValidatorPool $pool,
        public readonly ErrorFormatterInterface $errorFormatter,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private int $depth = 0,
        public readonly ?ValidatorMode $mode = null,
    ) {}

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
            errorFormatter: $errorFormatter ?? SimpleFormatter::shared(),
            nullableAsType: $nullableAsType,
            emptyArrayStrategy: $emptyArrayStrategy,
            mode: $mode,
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
}
