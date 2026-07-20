<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\Model\SchemaSiblingMerger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(SchemaSiblingMerger::class)]
final class SchemaSiblingMergerTest extends TestCase
{
    #[Test]
    public function merge_takes_stricter_min_length_when_sibling_is_looser(): void
    {
        $resolved = new Schema(type: 'string', minLength: 5);
        $sibling = new Schema(minLength: 3);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(5, $merged->minLength);
    }

    #[Test]
    public function merge_takes_stricter_max_length_when_sibling_is_looser(): void
    {
        $resolved = new Schema(type: 'string', maxLength: 10);
        $sibling = new Schema(maxLength: 20);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(10, $merged->maxLength);
    }

    #[Test]
    public function merge_minimum_and_maximum_take_stricter(): void
    {
        $resolved = new Schema(type: 'integer', minimum: 0.0, maximum: 100.0);
        $sibling = new Schema(minimum: -10.0, maximum: 200.0);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(0.0, $merged->minimum);
        self::assertSame(100.0, $merged->maximum);
    }

    #[Test]
    public function merge_exclusive_bounds_take_stricter(): void
    {
        $resolved = new Schema(exclusiveMinimum: 1.0, exclusiveMaximum: 99.0);
        $sibling = new Schema(exclusiveMinimum: -5.0, exclusiveMaximum: 1_000.0);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(1.0, $merged->exclusiveMinimum);
        self::assertSame(99.0, $merged->exclusiveMaximum);
    }

    #[Test]
    public function merge_items_and_properties_bounds_take_stricter(): void
    {
        $resolved = new Schema(
            minItems: 2,
            maxItems: 8,
            minProperties: 1,
            maxProperties: 5,
        );
        $sibling = new Schema(
            minItems: 1,
            maxItems: 12,
            minProperties: 3,
            maxProperties: 10,
        );

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(2, $merged->minItems);
        self::assertSame(8, $merged->maxItems);
        self::assertSame(3, $merged->minProperties);
        self::assertSame(5, $merged->maxProperties);
    }

    #[Test]
    public function merge_lower_bound_inherits_sibling_when_resolved_null(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema(minLength: 7);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(7, $merged->minLength);
    }

    #[Test]
    public function merge_upper_bound_inherits_resolved_when_sibling_null(): void
    {
        $resolved = new Schema(type: 'string', maxLength: 42);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(42, $merged->maxLength);
    }

    #[Test]
    public function merge_nullable_and_when_resolved_rejects_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: false);
        $sibling = new Schema(nullable: true);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertFalse($merged->nullable);
    }

    #[Test]
    public function merge_nullable_and_when_sibling_rejects_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: false);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertFalse($merged->nullable);
    }

    #[Test]
    public function merge_nullable_and_when_both_allow_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: true);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertTrue($merged->nullable);
    }

    #[Test]
    public function merge_any_of_wraps_in_all_of_not_concatenates(): void
    {
        $resolvedA = new Schema(type: 'string');
        $resolvedB = new Schema(type: 'integer');
        $siblingC = new Schema(type: 'boolean');
        $siblingD = new Schema(type: 'null');

        $resolved = new Schema(anyOf: [$resolvedA, $resolvedB]);
        $sibling = new Schema(anyOf: [$siblingC, $siblingD]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNull($merged->anyOf);
        self::assertNotNull($merged->allOf);
        self::assertCount(2, $merged->allOf);
        self::assertSame([$resolvedA, $resolvedB], $merged->allOf[0]->anyOf);
        self::assertSame([$siblingC, $siblingD], $merged->allOf[1]->anyOf);
    }

    #[Test]
    public function merge_one_of_wraps_in_all_of_not_concatenates(): void
    {
        $resolvedA = new Schema(type: 'string');
        $siblingC = new Schema(type: 'boolean');

        $resolved = new Schema(oneOf: [$resolvedA]);
        $sibling = new Schema(oneOf: [$siblingC]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNull($merged->oneOf);
        self::assertNotNull($merged->allOf);
        self::assertCount(2, $merged->allOf);
        self::assertSame([$resolvedA], $merged->allOf[0]->oneOf);
        self::assertSame([$siblingC], $merged->allOf[1]->oneOf);
    }

    #[Test]
    public function merge_any_of_passes_through_when_only_resolved_set(): void
    {
        $resolvedA = new Schema(type: 'string');
        $resolved = new Schema(anyOf: [$resolvedA]);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNull($merged->allOf);
        self::assertSame([$resolvedA], $merged->anyOf);
    }

    #[Test]
    public function merge_one_of_passes_through_when_only_sibling_set(): void
    {
        $resolved = new Schema(type: 'string');
        $siblingC = new Schema(type: 'boolean');
        $sibling = new Schema(oneOf: [$siblingC]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNull($merged->allOf);
        self::assertSame([$siblingC], $merged->oneOf);
    }

    #[Test]
    public function merge_composition_wrap_extends_existing_all_of(): void
    {
        $resolvedAllOfEntry = new Schema(type: 'string');
        $siblingAllOfEntry = new Schema(type: 'integer');
        $resolvedAnyOfA = new Schema(type: 'boolean');
        $siblingAnyOfC = new Schema(type: 'null');

        $resolved = new Schema(
            allOf: [$resolvedAllOfEntry],
            anyOf: [$resolvedAnyOfA],
        );
        $sibling = new Schema(
            allOf: [$siblingAllOfEntry],
            anyOf: [$siblingAnyOfC],
        );

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNull($merged->anyOf);
        self::assertCount(4, $merged->allOf);
        self::assertSame($resolvedAllOfEntry, $merged->allOf[0]);
        self::assertSame($siblingAllOfEntry, $merged->allOf[1]);
        self::assertSame([$resolvedAnyOfA], $merged->allOf[2]->anyOf);
        self::assertSame([$siblingAnyOfC], $merged->allOf[3]->anyOf);
    }

    #[Test]
    public function merge_prefix_items_recursive_by_index(): void
    {
        $resolvedFirstType = new Schema(type: 'integer');
        $siblingFirstType = new Schema(type: 'string');
        $resolvedSecondType = new Schema(type: 'boolean');

        $resolved = new Schema(prefixItems: [$resolvedFirstType, $resolvedSecondType]);
        $sibling = new Schema(prefixItems: [$siblingFirstType]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertCount(2, $merged->prefixItems);
        self::assertSame('string', $merged->prefixItems[0]->type);
        self::assertSame('boolean', $merged->prefixItems[1]->type);
    }

    #[Test]
    public function merge_prefix_items_recursive_merges_bounds(): void
    {
        $resolvedFirst = new Schema(type: 'integer', minimum: 0.0);
        $siblingFirst = new Schema(minimum: -10.0);
        $resolvedSecond = new Schema(type: 'string', minLength: 2);
        $siblingSecond = new Schema(minLength: 5);

        $resolved = new Schema(prefixItems: [$resolvedFirst, $resolvedSecond]);
        $sibling = new Schema(prefixItems: [$siblingFirst, $siblingSecond]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertCount(2, $merged->prefixItems);
        self::assertSame(0.0, $merged->prefixItems[0]->minimum);
        self::assertSame(5, $merged->prefixItems[1]->minLength);
    }

    #[Test]
    public function merge_prefix_items_appends_leftover_from_longer_side(): void
    {
        $siblingFirst = new Schema(type: 'string');
        $resolvedFirst = new Schema(type: 'integer');
        $resolvedSecond = new Schema(type: 'boolean');
        $resolvedThird = new Schema(type: 'null');

        $resolved = new Schema(prefixItems: [$resolvedFirst, $resolvedSecond, $resolvedThird]);
        $sibling = new Schema(prefixItems: [$siblingFirst]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertCount(3, $merged->prefixItems);
        self::assertSame('string', $merged->prefixItems[0]->type);
        self::assertSame($resolvedSecond, $merged->prefixItems[1]);
        self::assertSame($resolvedThird, $merged->prefixItems[2]);
    }

    #[Test]
    public function merge_prefix_items_when_only_resolved_set(): void
    {
        $item = new Schema(type: 'string');
        $resolved = new Schema(prefixItems: [$item]);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame([$item], $merged->prefixItems);
    }

    #[Test]
    public function merge_prefix_items_when_only_sibling_set(): void
    {
        $item = new Schema(type: 'string');
        $resolved = new Schema();
        $sibling = new Schema(prefixItems: [$item]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame([$item], $merged->prefixItems);
    }

    #[Test]
    public function merge_all_of_concatenates_unchanged(): void
    {
        $resolvedAllOfEntry = new Schema(type: 'string');
        $siblingAllOfEntry = new Schema(type: 'integer');

        $resolved = new Schema(allOf: [$resolvedAllOfEntry]);
        $sibling = new Schema(allOf: [$siblingAllOfEntry]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame([$resolvedAllOfEntry, $siblingAllOfEntry], $merged->allOf);
    }

    #[Test]
    public function merge_scalar_overrides_where_not_null(): void
    {
        $resolved = new Schema(type: 'string', format: 'date-time', pattern: '^a');
        $sibling = new Schema(type: 'integer', format: 'uuid', pattern: '^b');

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('integer', $merged->type);
        self::assertSame('uuid', $merged->format);
        self::assertSame('^b', $merged->pattern);
    }

    #[Test]
    public function merge_scalar_inherits_resolved_when_sibling_null(): void
    {
        $resolved = new Schema(type: 'string', format: 'date-time', pattern: '^a');
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('string', $merged->type);
        self::assertSame('date-time', $merged->format);
        self::assertSame('^a', $merged->pattern);
    }

    #[Test]
    public function merge_deprecated_or_semantics_sibling_true_tightens(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema(deprecated: true);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertTrue($merged->deprecated);
    }

    #[Test]
    public function merge_deprecated_stays_false_when_both_false(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertFalse($merged->deprecated);
        self::assertFalse($merged->readOnly);
        self::assertFalse($merged->writeOnly);
    }

    #[Test]
    public function merge_read_only_and_write_only_or_semantics(): void
    {
        $resolved = new Schema(type: 'string', readOnly: true);
        $sibling = new Schema(writeOnly: true);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertTrue($merged->readOnly);
        self::assertTrue($merged->writeOnly);
    }

    #[Test]
    public function merge_default_sibling_wins_when_has_default_true(): void
    {
        $resolved = new Schema(type: 'string', default: 'resolved', hasDefault: true);
        $sibling = new Schema(default: 'sibling', hasDefault: true);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('sibling', $merged->default);
        self::assertTrue($merged->hasDefault);
    }

    #[Test]
    public function merge_default_inherits_resolved_when_sibling_has_default_false(): void
    {
        $resolved = new Schema(type: 'string', default: 'resolved', hasDefault: true);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('resolved', $merged->default);
        self::assertTrue($merged->hasDefault);
    }

    #[Test]
    public function merge_const_sibling_wins_when_has_const_true(): void
    {
        $resolved = new Schema(type: 'string', const: 'resolved', hasConst: true);
        $sibling = new Schema(const: 'sibling', hasConst: true);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('sibling', $merged->const);
        self::assertTrue($merged->hasConst);
    }

    #[Test]
    public function merge_additional_properties_sibling_false_overrides_resolved_schema(): void
    {
        $resolvedAdditional = new Schema(type: 'object');
        $resolved = new Schema(type: 'object', additionalProperties: $resolvedAdditional);
        $sibling = new Schema(additionalProperties: false);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertFalse($merged->additionalProperties);
    }

    #[Test]
    public function merge_additional_properties_sibling_null_inherits_resolved(): void
    {
        $resolvedAdditional = new Schema(type: 'object');
        $resolved = new Schema(type: 'object', additionalProperties: $resolvedAdditional);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame($resolvedAdditional, $merged->additionalProperties);
    }

    #[Test]
    public function merge_required_unions_both_lists_with_unique_values(): void
    {
        $resolved = new Schema(type: 'object', required: ['id', 'name']);
        $sibling = new Schema(required: ['name', 'email']);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(['id', 'name', 'email'], $merged->required);
    }

    #[Test]
    public function merge_required_inherits_resolved_when_sibling_null(): void
    {
        $resolved = new Schema(type: 'object', required: ['id']);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame(['id'], $merged->required);
    }

    #[Test]
    public function merge_enum_intersects_via_json_equals_int_float_equivalence(): void
    {
        $resolved = new Schema(enum: [1, 2, 3]);
        $sibling = new Schema(enum: [1.0, 2]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNotNull($merged->enum);
        self::assertCount(2, $merged->enum);
        self::assertContains(1, $merged->enum);
        self::assertContains(2, $merged->enum);
    }

    #[Test]
    public function merge_enum_empty_intersection_preserved_as_empty_array(): void
    {
        $resolved = new Schema(enum: [1, 2]);
        $sibling = new Schema(enum: [3, 4]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertNotNull($merged->enum);
        self::assertSame([], $merged->enum);
    }

    #[Test]
    public function merge_enum_only_resolved_set_passes_through(): void
    {
        $resolved = new Schema(enum: [1, 2]);
        $sibling = new Schema();

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame([1, 2], $merged->enum);
    }

    #[Test]
    public function merge_enum_only_sibling_set_passes_through(): void
    {
        $resolved = new Schema();
        $sibling = new Schema(enum: [3, 4]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame([3, 4], $merged->enum);
    }

    #[Test]
    public function merge_title_prefers_sibling_ref_summary_over_sibling_title(): void
    {
        $resolved = new Schema(title: 'resolved-title');
        $sibling = new Schema(title: 'sibling-title', refSummary: 'ref-summary');

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('ref-summary', $merged->title);
    }

    #[Test]
    public function merge_title_prefers_sibling_title_over_resolved_title(): void
    {
        $resolved = new Schema(title: 'resolved-title');
        $sibling = new Schema(title: 'sibling-title');

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('sibling-title', $merged->title);
    }

    #[Test]
    public function merge_description_prefers_sibling_ref_description_over_resolved(): void
    {
        $resolved = new Schema(description: 'resolved-desc');
        $sibling = new Schema(description: 'sibling-desc', refDescription: 'ref-desc');

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame('ref-desc', $merged->description);
    }

    #[Test]
    public function merge_properties_map_sibling_wins_on_key_collision(): void
    {
        $resolvedProperty = new Schema(type: 'string');
        $siblingProperty = new Schema(type: 'integer');
        $resolved = new Schema(type: 'object', properties: ['name' => $resolvedProperty]);
        $sibling = new Schema(properties: ['name' => $siblingProperty]);

        $merged = (new SchemaSiblingMerger())->merge($resolved, $sibling);

        self::assertSame($siblingProperty, $merged->properties['name']);
    }
}
