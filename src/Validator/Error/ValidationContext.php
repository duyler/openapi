<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;

/**
 * Per-validation mutable state: breadcrumb stack (dataPath assembly),
 * recursion depth counter (MAX_DEPTH DoS guard), and JSON Schema 2020-12
 * annotation-state for unevaluatedProperties / unevaluatedItems tracking.
 *
 * Annotation contract (R3-SPEC-001..004): keywords that successfully
 * evaluate portions of the instance — properties, patternProperties,
 * additionalProperties, prefixItems, items, contains, and every
 * successful branch of an in-place applicator (allOf, anyOf, oneOf,
 * if/then/else, $ref) — register the evaluated property names / item
 * indices here. unevaluatedProperties / unevaluatedItems read the
 * resulting sets to decide what remains unevaluated. `not` registers
 * nothing: per §10.3.4 it contributes an empty annotation set.
 *
 * Concurrency contract (P-062): one instance per validate() entry-point
 * call. The instance MUST NOT be stored as a property on a shared
 * validator — it is passed as a method argument through the recursion
 * chain. Under prefork (PHP-FPM, RoadRunner) each request implicitly
 * gets a fresh call stack. Under shared-validator runtimes (Swoole
 * coroutines, FrankenPHP threaded workers) the same validator instance
 * handles many sequential requests, but each validate() call creates a
 * fresh context — coroutine A and coroutine B never observe each
 * other's breadcrumbs, depth, or annotation state.
 */
final class ValidationContext
{
    public const int MAX_DEPTH = 64;

    /** @var array<string, true> */
    private array $evaluatedPropertyNames = [];

    /** @var array<int, true> */
    private array $evaluatedItemIndices = [];

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
            errorFormatter: $errorFormatter ?? new SimpleFormatter(),
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

    /**
     * Merges annotation state from a child branch context (composition
     * sub-validation) into this context. Called after a successful
     * sub-validation in AllOf / AnyOf / OneOf / If / Then / Else.
     *
     * Failed branches MUST NOT have their annotations merged — only
     * successfully validated subschemas contribute to the evaluated set
     * per JSON Schema 2020-12 §10.3.4 / §11.1.1.3.
     */
    public function mergeChildAnnotations(self $child): void
    {
        $this->evaluatedPropertyNames += $child->evaluatedPropertyNames;
        $this->evaluatedItemIndices += $child->evaluatedItemIndices;
    }

    /**
     * Creates a child context sharing breadcrumbs and depth with this
     * context but starting with empty annotation state. Used by
     * composition validators to isolate annotations of each branch
     * from siblings until a branch succeeds.
     */
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
