<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Internal\ScalarSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\SiblingMergeContext;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ScalarSiblingMerger::class)]
final class ScalarSiblingMergerTest extends TestCase
{
    #[Test]
    public function merge_returns_overrides_for_eight_scalar_fields(): void
    {
        $resolved = new Schema(type: 'string', format: 'date-time', pattern: '^a', nullable: true);
        $sibling = new Schema(type: 'string', format: 'date-time', nullable: true);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame('string', $overrides['type']);
        self::assertSame('date-time', $overrides['format']);
        // pattern: resolved has it, sibling does not → mergeNullableIdentical returns resolved value
        self::assertSame('^a', $overrides['pattern']);
        self::assertTrue($overrides['nullable']);
        self::assertFalse($overrides['hasConst']);
        self::assertNull($overrides['multipleOf']);
        self::assertNull($overrides['enum']);
        self::assertArrayHasKey('const', $overrides);
    }

    #[Test]
    public function merge_nullable_keeps_resolved_nullable_when_sibling_omits_it(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: false);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertTrue($overrides['nullable']);
    }

    #[Test]
    public function merge_nullable_sibling_widens_non_nullable_resolved(): void
    {
        $resolved = new Schema(type: 'string', nullable: false);
        $sibling = new Schema(nullable: true);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertTrue($overrides['nullable']);
    }

    #[Test]
    public function merge_nullable_both_true_allows_null(): void
    {
        $resolved = new Schema(type: 'string', nullable: true);
        $sibling = new Schema(nullable: true);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertTrue($overrides['nullable']);
    }

    #[Test]
    public function merge_nullable_stays_false_when_neither_side_is_nullable(): void
    {
        $resolved = new Schema(type: 'string', nullable: false);
        $sibling = new Schema(nullable: false);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertFalse($overrides['nullable']);
    }

    #[Test]
    public function merge_format_returns_null_when_formats_differ(): void
    {
        $resolved = new Schema(type: 'string', format: 'date-time');
        $sibling = new Schema(format: 'uuid');

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['format']);
    }

    #[Test]
    public function merge_format_returns_format_when_equal(): void
    {
        $resolved = new Schema(type: 'string', format: 'email');
        $sibling = new Schema(format: 'email');

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame('email', $overrides['format']);
    }

    #[Test]
    public function merge_type_returns_null_when_no_intersection(): void
    {
        $resolved = new Schema(type: 'string');
        $sibling = new Schema(type: 'integer');

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['type']);
    }

    #[Test]
    public function merge_type_returns_single_type_when_one_intersecting(): void
    {
        $resolved = new Schema(type: ['string', 'integer']);
        $sibling = new Schema(type: ['integer', 'boolean']);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame('integer', $overrides['type']);
    }

    #[Test]
    public function merge_type_returns_array_when_multiple_intersecting(): void
    {
        $resolved = new Schema(type: ['string', 'integer', 'boolean']);
        $sibling = new Schema(type: ['integer', 'boolean', 'null']);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(['integer', 'boolean'], $overrides['type']);
    }

    #[Test]
    public function merge_enum_uses_json_equals_intersection(): void
    {
        $resolved = new Schema(enum: [1, 'a', true]);
        $sibling = new Schema(enum: [1, 'b', true, 2]);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        // JSON equality: 1 === true is NOT equal in JsonEquals (different types).
        // True (bool) is equal in both. So intersection is [1, true].
        self::assertCount(2, $overrides['enum']);
    }

    #[Test]
    public function merge_const_prefers_sibling_when_sibling_has_const(): void
    {
        $resolved = new Schema(const: 'resolved');
        $sibling = new Schema(const: 'sibling', hasConst: true);

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame('sibling', $overrides['const']);
        self::assertTrue($overrides['hasConst']);
    }

    #[Test]
    public function merge_nullable_identical_returns_null_when_both_non_null(): void
    {
        $resolved = new Schema(multipleOf: 2.0, pattern: '^a');
        $sibling = new Schema(multipleOf: 3.0, pattern: '^b');

        $overrides = new ScalarSiblingMerger()->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['multipleOf']);
        self::assertNull($overrides['pattern']);
    }

    #[Test]
    public function collect_scalar_field_additions_returns_both_multiple_of_when_set(): void
    {
        $resolved = new Schema(multipleOf: 2.0);
        $sibling = new Schema(multipleOf: 3.0);

        $additions = new ScalarSiblingMerger()
            ->collectScalarFieldAdditions($resolved, $sibling);

        self::assertCount(2, $additions);
        self::assertSame(2.0, $additions[0]->multipleOf);
        self::assertSame(3.0, $additions[1]->multipleOf);
    }

    #[Test]
    public function collect_scalar_field_additions_returns_both_pattern_when_set(): void
    {
        $resolved = new Schema(pattern: '^a');
        $sibling = new Schema(pattern: '^b');

        $additions = new ScalarSiblingMerger()
            ->collectScalarFieldAdditions($resolved, $sibling);

        self::assertCount(2, $additions);
        self::assertSame('^a', $additions[0]->pattern);
        self::assertSame('^b', $additions[1]->pattern);
    }

    #[Test]
    public function collect_scalar_field_additions_empty_when_only_one_set(): void
    {
        $resolved = new Schema(multipleOf: 2.0, pattern: '^a');
        $sibling = new Schema();

        $additions = new ScalarSiblingMerger()
            ->collectScalarFieldAdditions($resolved, $sibling);

        self::assertSame([], $additions);
    }

    #[Test]
    public function merge_string_list_dedupes_and_unions(): void
    {
        $merger = new ScalarSiblingMerger();

        $result = $merger->mergeStringList(['a', 'b'], ['b', 'c']);

        self::assertSame(['a', 'b', 'c'], $result);
    }

    #[Test]
    public function merge_mixed_map_unions_with_sibling_overriding_duplicates(): void
    {
        $merger = new ScalarSiblingMerger();

        $result = $merger->mergeMixedMap(['x' => 1, 'y' => 2], ['y' => 3, 'z' => 4]);

        self::assertSame(['x' => 1, 'y' => 3, 'z' => 4], $result);
    }
}
