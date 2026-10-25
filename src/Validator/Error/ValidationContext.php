<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Error;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\ContentValidationResult;
use Duyler\OpenApi\Validator\Dto\DialectProfile;
use Duyler\OpenApi\Validator\Dto\ValidationAnnotations;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\Formatter\ErrorFormatterInterface;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Exception\SchemaDepthExceededException;
use Duyler\OpenApi\Validator\ValidatorMode;
use Duyler\OpenApi\Validator\ValidatorPool;
use WeakMap;

use function array_pop;
use function count;

final class ValidationContext
{
    public const int MAX_DEPTH = 64;

    public readonly DialectProfile $dialectProfile;

    /** @var array<string, array<string, true>> */
    private array $evaluatedPropertyNames = [];

    /** @var array<string, array<int, true>> */
    private array $evaluatedItemIndices = [];

    private ValidationAnnotations $annotations;

    private ?ContentValidationResult $contentResult = null;

    /** @var WeakMap<Schema, string> */
    private WeakMap $dynamicRefTargets;

    private function __construct(
        public readonly BreadcrumbManager $breadcrumbs,
        public readonly ValidatorPool $pool,
        public readonly ErrorFormatterInterface $errorFormatter,
        public readonly bool $nullableAsType = true,
        public readonly EmptyArrayStrategy $emptyArrayStrategy = EmptyArrayStrategy::AllowBoth,
        private int $depth = 0,
        public readonly ?ValidatorMode $mode = null,
        /** @var list<Schema> */
        private array $dynamicScope = [],
        ?DialectProfile $dialectProfile = null,
        public readonly bool $skipContentMediaType = false,
    ) {
        $this->dialectProfile = $dialectProfile ?? DialectProfile::forOpenApi31();
        $this->annotations = new ValidationAnnotations();
        /** @var WeakMap<Schema, string> $dynamicRefTargets */
        $dynamicRefTargets = new WeakMap();
        $this->dynamicRefTargets = $dynamicRefTargets;
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
            dialectProfile: $init->dialectProfile,
            skipContentMediaType: $init->skipContentMediaType,
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
        $this->evaluatedPropertyNames[$this->breadcrumbs->currentPath()][$name] = true;
    }

    public function markItemEvaluated(int $index): void
    {
        $this->evaluatedItemIndices[$this->breadcrumbs->currentPath()][$index] = true;
    }

    /** @return list<string> */
    public function evaluatedPropertyNames(): array
    {
        return array_keys($this->evaluatedPropertyNames[$this->breadcrumbs->currentPath()] ?? []);
    }

    /** @return list<int> */
    public function evaluatedItemIndices(): array
    {
        return array_keys($this->evaluatedItemIndices[$this->breadcrumbs->currentPath()] ?? []);
    }

    public function hasPropertyBeenEvaluated(string $name): bool
    {
        return isset($this->evaluatedPropertyNames[$this->breadcrumbs->currentPath()][$name]);
    }

    public function hasItemBeenEvaluated(int $index): bool
    {
        return isset($this->evaluatedItemIndices[$this->breadcrumbs->currentPath()][$index]);
    }

    public function path(): string
    {
        return $this->breadcrumbs->currentPath();
    }

    public function annotate(string $keyword, mixed $value, string $path): void
    {
        $this->annotations = $this->annotations->with($keyword, $path, $value);
    }

    public function attachContentResult(ContentValidationResult $result): void
    {
        $this->contentResult ??= $result;
    }

    public function contentResult(): ?ContentValidationResult
    {
        return $this->contentResult;
    }

    /**
     * @return array<string, list<array{path: string, value: mixed}>>
     */
    public function annotations(): array
    {
        return $this->annotations->toArray();
    }

    public function annotateSchemaExtras(Schema $schema): void
    {
        /** @var mixed $value */
        foreach ($schema->extras ?? [] as $keyword => $value) {
            $this->annotate($keyword, $value, $this->path());
        }
    }

    public function mergeChildAnnotations(self $child): void
    {
        foreach ($child->evaluatedPropertyNames as $path => $names) {
            $this->evaluatedPropertyNames[$path] = ($this->evaluatedPropertyNames[$path] ?? []) + $names;
        }

        foreach ($child->evaluatedItemIndices as $path => $indices) {
            $this->evaluatedItemIndices[$path] = ($this->evaluatedItemIndices[$path] ?? []) + $indices;
        }

        $this->annotations = $this->annotations->merge($child->annotations);
        $this->contentResult ??= $child->contentResult;
    }

    public function enterDynamicScope(Schema $resource): void
    {
        if ([] !== $this->dynamicScope && $this->dynamicScope[count($this->dynamicScope) - 1] === $resource) {
            return;
        }

        $this->dynamicScope[] = $resource;
    }

    public function leaveDynamicScope(): void
    {
        array_pop($this->dynamicScope);
    }

    public function hasEmptyDynamicScope(): bool
    {
        return [] === $this->dynamicScope;
    }

    /** @return list<Schema> */
    public function dynamicScope(): array
    {
        return $this->dynamicScope;
    }

    public function resolveDynamicAnchor(string $name): ?Schema
    {
        foreach ($this->dynamicScope as $resource) {
            if ($name === $resource->dynamicAnchor) {
                return $resource;
            }
        }

        return null;
    }

    public function enterDynamicRefTarget(Schema $target, string $path): bool
    {
        if (isset($this->dynamicRefTargets[$target]) && $this->dynamicRefTargets[$target] === $path) {
            return false;
        }

        $this->dynamicRefTargets[$target] = $path;

        return true;
    }

    public function leaveDynamicRefTarget(Schema $target): void
    {
        unset($this->dynamicRefTargets[$target]);
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
            dynamicScope: $this->dynamicScope,
            dialectProfile: $this->dialectProfile,
            skipContentMediaType: $this->skipContentMediaType,
        );
    }
}
