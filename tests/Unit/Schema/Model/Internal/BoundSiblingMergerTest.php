<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Internal\BoundSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\SiblingMergeContext;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(BoundSiblingMerger::class)]
final class BoundSiblingMergerTest extends TestCase
{
    private BoundSiblingMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new BoundSiblingMerger();
    }

    #[Test]
    public function merge_lower_bound_takes_stricter_minimum(): void
    {
        $resolved = new Schema(type: 'integer', minimum: 0.0);
        $sibling = new Schema(minimum: -10.0);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(0.0, $overrides['minimum']);
    }

    #[Test]
    public function merge_upper_bound_takes_stricter_maximum(): void
    {
        $resolved = new Schema(type: 'integer', maximum: 100.0);
        $sibling = new Schema(maximum: 200.0);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(100.0, $overrides['maximum']);
    }

    #[Test]
    public function merge_min_length_uses_lower_bound_semantics(): void
    {
        $resolved = new Schema(type: 'string', minLength: 5);
        $sibling = new Schema(minLength: 3);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(5, $overrides['minLength']);
    }

    #[Test]
    public function merge_max_length_uses_upper_bound_semantics(): void
    {
        $resolved = new Schema(type: 'string', maxLength: 10);
        $sibling = new Schema(maxLength: 20);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(10, $overrides['maxLength']);
    }

    #[Test]
    public function merge_items_bounds_take_stricter(): void
    {
        $resolved = new Schema(minItems: 2, maxItems: 8);
        $sibling = new Schema(minItems: 1, maxItems: 12);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(2, $overrides['minItems']);
        self::assertSame(8, $overrides['maxItems']);
    }

    #[Test]
    public function merge_properties_bounds_take_stricter(): void
    {
        $resolved = new Schema(minProperties: 1, maxProperties: 5);
        $sibling = new Schema(minProperties: 3, maxProperties: 10);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(3, $overrides['minProperties']);
        self::assertSame(5, $overrides['maxProperties']);
    }

    #[Test]
    public function merge_lower_bound_inherits_sibling_when_resolved_null(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema(minLength: 7);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(7, $overrides['minLength']);
    }

    #[Test]
    public function merge_upper_bound_inherits_resolved_when_sibling_null(): void
    {
        $resolved = new Schema(type: 'string', maxLength: 42);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(42, $overrides['maxLength']);
    }

    #[Test]
    public function merge_schema_or_bool_returns_false_when_sibling_is_false(): void
    {
        $resolved = new Schema(items: new Schema(type: 'string'));
        $sibling = new Schema(items: false);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertFalse($overrides['items']);
    }

    #[Test]
    public function merge_schema_or_bool_returns_false_when_resolved_is_false(): void
    {
        $resolved = new Schema(items: false);
        $sibling = new Schema(items: new Schema(type: 'string'));

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertFalse($overrides['items']);
    }

    #[Test]
    public function merge_schema_or_bool_sibling_true_returns_resolved(): void
    {
        $itemSchema = new Schema(type: 'string');
        $resolved = new Schema(items: $itemSchema);
        $sibling = new Schema(items: true);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        // §3 fix: `is_bool($sibling) && $sibling` (was: `true === $sibling`)
        // Must distinguish true from truthy non-bool (Schema instance is truthy too).
        self::assertSame($itemSchema, $overrides['items']);
    }

    #[Test]
    public function merge_schema_or_bool_resolved_true_returns_sibling(): void
    {
        $itemSchema = new Schema(type: 'integer');
        $resolved = new Schema(items: true);
        $sibling = new Schema(items: $itemSchema);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        // §3 fix: `is_bool($resolved) && $resolved` (was: `true === $resolved`)
        self::assertSame($itemSchema, $overrides['items']);
    }

    #[Test]
    public function merge_schema_or_bool_null_sibling_returns_resolved(): void
    {
        $itemSchema = new Schema(type: 'string');
        $resolved = new Schema(items: $itemSchema);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame($itemSchema, $overrides['items']);
    }

    #[Test]
    public function merge_schema_or_bool_null_resolved_returns_sibling(): void
    {
        $itemSchema = new Schema(type: 'integer');
        $resolved = new Schema();
        $sibling = new Schema(items: $itemSchema);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame($itemSchema, $overrides['items']);
    }

    #[Test]
    public function merge_schema_or_bool_both_schemas_recursively_merges(): void
    {
        $resolved = new Schema(items: new Schema(type: 'string', minLength: 2));
        $sibling = new Schema(items: new Schema(minLength: 5));

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        $merged = $overrides['items'];
        self::assertInstanceOf(Schema::class, $merged);
        self::assertSame('string', $merged->type);
        // minLength takes stricter (5 > 2)
        self::assertSame(5, $merged->minLength);
    }

    #[Test]
    public function merge_unique_items_uses_null_coalesce_sibling_prefers(): void
    {
        $resolved = new Schema(uniqueItems: false);
        $sibling = new Schema(uniqueItems: true);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertTrue($overrides['uniqueItems']);
    }

    #[Test]
    public function merge_unique_items_inherits_resolved_when_sibling_null(): void
    {
        $resolved = new Schema(uniqueItems: true);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertTrue($overrides['uniqueItems']);
    }

    #[Test]
    public function merge_prefix_items_recursive_merges_overlap(): void
    {
        $resolved = new Schema(prefixItems: [new Schema(type: 'integer', minimum: 0.0)]);
        $sibling = new Schema(prefixItems: [new Schema(minimum: -10.0)]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        $prefixItems = $overrides['prefixItems'];
        self::assertCount(1, $prefixItems);
        self::assertSame(0.0, $prefixItems[0]->minimum);
        self::assertSame('integer', $prefixItems[0]->type);
    }

    #[Test]
    public function merge_prefix_items_appends_leftover_from_longer_resolved(): void
    {
        $resolvedSecond = new Schema(type: 'boolean');
        $resolved = new Schema(prefixItems: [new Schema(type: 'integer'), $resolvedSecond]);
        $sibling = new Schema(prefixItems: [new Schema(type: 'string')]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        $prefixItems = $overrides['prefixItems'];
        self::assertCount(2, $prefixItems);
        // Index 0 merged recursively (integer ∩ string → null type, but allOf has both)
        self::assertNotNull($prefixItems[0]->allOf);
        // Index 1 appended from resolved (sibling had only 1)
        self::assertSame($resolvedSecond, $prefixItems[1]);
    }

    #[Test]
    public function merge_prefix_items_passes_through_when_only_resolved_set(): void
    {
        $item = new Schema(type: 'string');
        $resolved = new Schema(prefixItems: [$item]);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame([$item], $overrides['prefixItems']);
    }

    #[Test]
    public function merge_prefix_items_passes_through_when_only_sibling_set(): void
    {
        $item = new Schema(type: 'string');
        $resolved = new Schema();
        $sibling = new Schema(prefixItems: [$item]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame([$item], $overrides['prefixItems']);
    }

    #[Test]
    public function merge_returns_overrides_for_all_twenty_fields(): void
    {
        $resolved = new Schema(maximum: 100.0);
        $sibling = new Schema(maximum: 50.0);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        $expectedKeys = [
            'maximum', 'exclusiveMaximum', 'minimum', 'exclusiveMinimum',
            'maxLength', 'minLength', 'maxItems', 'minItems',
            'maxProperties', 'minProperties', 'uniqueItems',
            'not', 'items', 'additionalProperties', 'unevaluatedProperties',
            'contains', 'propertyNames', 'unevaluatedItems', 'contentSchema',
            'prefixItems',
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $overrides, "Field {$key} missing from bound overrides");
        }
    }
}
