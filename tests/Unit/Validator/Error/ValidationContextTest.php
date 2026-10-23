<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Validator\Error;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Dto\ContentValidationResult;
use Duyler\OpenApi\Validator\Error\Formatter\DetailedFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\JsonFormatter;
use Duyler\OpenApi\Validator\Error\Formatter\SimpleFormatter;
use Duyler\OpenApi\Validator\Error\Internal\ValidationContextInit;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ValidationContextTest extends TestCase
{
    #[Test]
    public function create_context(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $this->assertSame($pool, $context->pool);
        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function enter_and_leave_breadcrumb(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $context->enterBreadcrumb('users');

        $this->assertSame('/users', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();

        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function enter_breadcrumb_index(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $context->enterBreadcrumbIndex(0);

        $this->assertSame('/0', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function chain_breadcrumbs_with_push_pop(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $context->enterBreadcrumb('users');
        $this->assertSame('/users', $context->breadcrumbs->currentPath());

        $context->enterBreadcrumbIndex(0);
        $this->assertSame('/users/0', $context->breadcrumbs->currentPath());

        $context->enterBreadcrumb('name');
        $this->assertSame('/users/0/name', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $this->assertSame('/users/0', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $this->assertSame('/users', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $this->assertSame('/', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function maintain_pool_across_mutations(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $this->assertSame($pool, $context->pool);

        $context->enterBreadcrumb('users');

        $this->assertSame($pool, $context->pool);
    }

    #[Test]
    public function create_context_with_default_formatter(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $this->assertInstanceOf(SimpleFormatter::class, $context->errorFormatter);
    }

    #[Test]
    public function maintain_formatter_across_mutations(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: $formatter,
        ));

        $context->enterBreadcrumb('users');

        $this->assertSame($formatter, $context->errorFormatter);
    }

    #[Test]
    public function use_custom_formatter(): void
    {
        $pool = new ValidatorPool();
        $formatter = new JsonFormatter();
        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: $formatter,
        ));

        $this->assertSame($formatter, $context->errorFormatter);
    }

    #[Test]
    public function maintain_formatter_with_breadcrumb_index(): void
    {
        $pool = new ValidatorPool();
        $formatter = new DetailedFormatter();
        $context = ValidationContext::fromInit(new ValidationContextInit(
            pool: $pool,
            errorFormatter: $formatter,
        ));

        $context->enterBreadcrumbIndex(0);

        $this->assertSame($formatter, $context->errorFormatter);
    }

    #[Test]
    public function depth_increment_and_decrement(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $this->assertSame(0, $context->depth());

        $context->incrementDepth();
        $this->assertSame(1, $context->depth());

        $context->incrementDepth();
        $this->assertSame(2, $context->depth());

        $context->decrementDepth();
        $this->assertSame(1, $context->depth());

        $context->decrementDepth();
        $this->assertSame(0, $context->depth());
    }

    #[Test]
    public function breadcrumb_restored_after_pop(): void
    {
        $pool = new ValidatorPool();
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));

        $context->enterBreadcrumb('a');
        $context->enterBreadcrumb('b');
        $context->enterBreadcrumb('c');

        $this->assertSame('/a/b/c', $context->breadcrumbs->currentPath());

        $context->leaveBreadcrumb();
        $context->leaveBreadcrumb();

        $this->assertSame('/a', $context->breadcrumbs->currentPath());
    }

    #[Test]
    public function mark_property_evaluated_tracks_unique_names(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $context->markPropertyEvaluated('name');
        $context->markPropertyEvaluated('id');
        $context->markPropertyEvaluated('name');

        $this->assertSame(['name', 'id'], $context->evaluatedPropertyNames());
    }

    #[Test]
    public function mark_item_evaluated_tracks_unique_indices(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $context->markItemEvaluated(0);
        $context->markItemEvaluated(2);
        $context->markItemEvaluated(0);

        $this->assertSame([0, 2], $context->evaluatedItemIndices());
    }

    #[Test]
    public function has_property_been_evaluated_returns_true_only_for_registered_names(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $context->markPropertyEvaluated('known');

        $this->assertTrue($context->hasPropertyBeenEvaluated('known'));
        $this->assertFalse($context->hasPropertyBeenEvaluated('unknown'));
    }

    #[Test]
    public function has_item_been_evaluated_returns_true_only_for_registered_indices(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $context->markItemEvaluated(3);

        $this->assertTrue($context->hasItemBeenEvaluated(3));
        $this->assertFalse($context->hasItemBeenEvaluated(4));
    }

    #[Test]
    public function fork_for_branch_starts_with_empty_annotation_state(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $parent->markPropertyEvaluated('parent_only');
        $parent->markItemEvaluated(0);

        $child = $parent->forkForBranch();

        $this->assertSame([], $child->evaluatedPropertyNames());
        $this->assertSame([], $child->evaluatedItemIndices());
    }

    #[Test]
    public function fork_for_branch_shares_breadcrumbs_and_pool_with_parent(): void
    {
        $pool = new ValidatorPool();
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: $pool));
        $parent->enterBreadcrumb('users');

        $child = $parent->forkForBranch();

        $this->assertSame($pool, $child->pool);
        $this->assertSame('/users', $child->breadcrumbs->currentPath());
    }

    #[Test]
    public function merge_child_annotations_unions_property_names_and_item_indices(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $parent->markPropertyEvaluated('parent');
        $parent->markItemEvaluated(0);

        $child = $parent->forkForBranch();
        $child->markPropertyEvaluated('child');
        $child->markItemEvaluated(2);

        $parent->mergeChildAnnotations($child);

        $this->assertSame(['parent', 'child'], $parent->evaluatedPropertyNames());
        $this->assertSame([0, 2], $parent->evaluatedItemIndices());
    }

    #[Test]
    public function merge_child_annotations_does_not_mutate_child_state(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $parent->markPropertyEvaluated('parent_only');

        $child = $parent->forkForBranch();
        $child->markPropertyEvaluated('child_only');

        $parent->mergeChildAnnotations($child);

        $this->assertSame(['child_only'], $child->evaluatedPropertyNames());
        $this->assertSame(['parent_only', 'child_only'], $parent->evaluatedPropertyNames());
    }

    #[Test]
    public function path_returns_current_breadcrumb_path(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $this->assertSame('/', $context->path());

        $context->enterBreadcrumb('users');
        $context->enterBreadcrumbIndex(0);

        $this->assertSame('/users/0', $context->path());
    }

    #[Test]
    public function annotations_are_empty_by_default(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $this->assertSame([], $context->annotations());
    }

    #[Test]
    public function annotate_accumulates_records_per_keyword(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $context->annotate('x-a', 1, '/');
        $context->annotate('x-a', 2, '/users');
        $context->annotate('x-b', 'v', '/users/0');

        $this->assertSame([
            'x-a' => [
                ['path' => '/', 'value' => 1],
                ['path' => '/users', 'value' => 2],
            ],
            'x-b' => [
                ['path' => '/users/0', 'value' => 'v'],
            ],
        ], $context->annotations());
    }

    #[Test]
    public function annotate_schema_extras_records_each_keyword_with_current_path(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $schema = new Schema(extras: ['x-meta' => ['a' => 1], 'x-vendor' => true]);

        $context->enterBreadcrumb('users');
        $context->annotateSchemaExtras($schema);

        $this->assertSame([
            'x-meta' => [
                ['path' => '/users', 'value' => ['a' => 1]],
            ],
            'x-vendor' => [
                ['path' => '/users', 'value' => true],
            ],
        ], $context->annotations());
    }

    #[Test]
    public function annotate_schema_extras_ignores_schema_without_extras(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $context->annotateSchemaExtras(new Schema(type: 'string'));

        $this->assertSame([], $context->annotations());
    }

    #[Test]
    public function fork_for_branch_starts_with_empty_annotations(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $parent->annotate('x-parent', 'v', '/');

        $child = $parent->forkForBranch();

        $this->assertSame([], $child->annotations());
    }

    #[Test]
    public function merge_child_annotations_appends_child_records_per_keyword(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $parent->annotate('x-meta', 'parent', '/');

        $child = $parent->forkForBranch();
        $child->annotate('x-meta', 'child', '/users');
        $child->annotate('x-ok', 'branch', '/users');

        $parent->mergeChildAnnotations($child);

        $this->assertSame([
            'x-meta' => [
                ['path' => '/', 'value' => 'parent'],
                ['path' => '/users', 'value' => 'child'],
            ],
            'x-ok' => [
                ['path' => '/users', 'value' => 'branch'],
            ],
        ], $parent->annotations());
    }

    #[Test]
    public function merge_child_annotations_does_not_mutate_child_annotations(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $parent->annotate('x-parent', 'v', '/');

        $child = $parent->forkForBranch();
        $child->annotate('x-child', 'v', '/users');

        $parent->mergeChildAnnotations($child);

        $this->assertSame([
            'x-child' => [
                ['path' => '/users', 'value' => 'v'],
            ],
        ], $child->annotations());
    }

    #[Test]
    public function dynamic_scope_is_empty_by_default(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $this->assertTrue($context->hasEmptyDynamicScope());
        $this->assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function enter_and_leave_dynamic_scope_push_and_pop_resource(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $resource = new Schema(id: 'https://example.com/a');

        $context->enterDynamicScope($resource);

        $this->assertFalse($context->hasEmptyDynamicScope());
        $this->assertSame([$resource], $context->dynamicScope());

        $context->leaveDynamicScope();

        $this->assertTrue($context->hasEmptyDynamicScope());
        $this->assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function enter_dynamic_scope_deduplicates_consecutive_identical_resource(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $resource = new Schema(id: 'https://example.com/a');

        $context->enterDynamicScope($resource);
        $context->enterDynamicScope($resource);

        $this->assertSame([$resource], $context->dynamicScope());
    }

    #[Test]
    public function enter_dynamic_scope_keeps_repeated_resource_after_intermediate_frame(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $first = new Schema(id: 'https://example.com/a');
        $second = new Schema(id: 'https://example.com/b');

        $context->enterDynamicScope($first);
        $context->enterDynamicScope($second);
        $context->enterDynamicScope($first);

        $this->assertSame([$first, $second, $first], $context->dynamicScope());
    }

    #[Test]
    public function leave_dynamic_scope_on_empty_scope_is_no_op(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $context->leaveDynamicScope();

        $this->assertTrue($context->hasEmptyDynamicScope());
        $this->assertSame([], $context->dynamicScope());
    }

    #[Test]
    public function resolve_dynamic_anchor_returns_outermost_resource_with_matching_anchor(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $outer = new Schema(id: 'https://example.com/strict-tree', dynamicAnchor: 'node');
        $inner = new Schema(id: 'https://example.com/tree', dynamicAnchor: 'node');

        $context->enterDynamicScope($outer);
        $context->enterDynamicScope($inner);

        $this->assertSame($outer, $context->resolveDynamicAnchor('node'));
    }

    #[Test]
    public function resolve_dynamic_anchor_returns_null_when_anchor_absent(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $context->enterDynamicScope(new Schema(id: 'https://example.com/a', dynamicAnchor: 'other'));

        $this->assertNull($context->resolveDynamicAnchor('node'));
    }

    #[Test]
    public function resolve_dynamic_anchor_on_empty_scope_returns_null(): void
    {
        $context = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));

        $this->assertNull($context->resolveDynamicAnchor('node'));
    }

    #[Test]
    public function fork_for_branch_copies_dynamic_scope_from_parent(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $root = new Schema(id: 'https://example.com/root');
        $parent->enterDynamicScope($root);

        $child = $parent->forkForBranch();

        $this->assertSame([$root], $child->dynamicScope());
    }

    #[Test]
    public function fork_for_branch_isolates_child_scope_changes_from_parent(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $root = new Schema(id: 'https://example.com/root');
        $branch = new Schema(id: 'https://example.com/branch');
        $parent->enterDynamicScope($root);

        $child = $parent->forkForBranch();
        $child->enterDynamicScope($branch);

        $this->assertSame([$root, $branch], $child->dynamicScope());
        $this->assertSame([$root], $parent->dynamicScope());

        $child->leaveDynamicScope();

        $this->assertSame([$root], $child->dynamicScope());
        $this->assertSame([$root], $parent->dynamicScope());
    }

    #[Test]
    public function content_result_from_merged_branch_is_readable_from_parent(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $child = $parent->forkForBranch();
        $result = ContentValidationResult::eager(['a' => 1], true, []);

        $child->attachContentResult($result);
        $this->assertNull($parent->contentResult());

        $parent->mergeChildAnnotations($child);

        $this->assertSame($result, $parent->contentResult());
    }

    #[Test]
    public function content_result_of_unmerged_branch_stays_hidden_and_first_attachment_wins(): void
    {
        $parent = ValidationContext::fromInit(new ValidationContextInit(pool: new ValidatorPool()));
        $failedBranch = $parent->forkForBranch();
        $failedBranch->attachContentResult(ContentValidationResult::eager('failed', true, []));

        $this->assertNull($parent->contentResult());

        $first = ContentValidationResult::eager('first', true, []);
        $parent->attachContentResult($first);

        $secondBranch = $parent->forkForBranch();
        $secondBranch->attachContentResult(ContentValidationResult::eager('second', true, []));

        $parent->mergeChildAnnotations($secondBranch);

        $this->assertSame($first, $parent->contentResult());
    }
}
