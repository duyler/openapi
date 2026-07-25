<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Schema\Model\Internal;

use Duyler\OpenApi\Schema\Model\Internal\CompositionSiblingMerger;
use Duyler\OpenApi\Schema\Model\Internal\SiblingMergeContext;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompositionSiblingMerger::class)]
final class CompositionSiblingMergerTest extends TestCase
{
    private CompositionSiblingMerger $merger;

    protected function setUp(): void
    {
        $this->merger = new CompositionSiblingMerger();
    }

    #[Test]
    public function merge_all_of_concatenates_when_both_set(): void
    {
        $resolvedAllOf = new Schema(type: 'string');
        $siblingAllOf = new Schema(type: 'integer');
        $resolved = new Schema(allOf: [$resolvedAllOf]);
        $sibling = new Schema(allOf: [$siblingAllOf]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame([$resolvedAllOf, $siblingAllOf], $overrides['allOf']);
    }

    #[Test]
    public function merge_all_of_passes_through_when_only_resolved_set(): void
    {
        $resolvedAllOf = new Schema(type: 'string');
        $resolved = new Schema(allOf: [$resolvedAllOf]);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame([$resolvedAllOf], $overrides['allOf']);
    }

    #[Test]
    public function merge_any_of_wraps_in_all_of_when_both_set(): void
    {
        $resolvedA = new Schema(type: 'string');
        $resolvedB = new Schema(type: 'integer');
        $siblingC = new Schema(type: 'boolean');
        $siblingD = new Schema(type: 'null');
        $resolved = new Schema(anyOf: [$resolvedA, $resolvedB]);
        $sibling = new Schema(anyOf: [$siblingC, $siblingD]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        // anyOf becomes null when both sides set (deferred to allOf as wrapped composition)
        self::assertNull($overrides['anyOf']);
        // allOf contains 2 wrapper schemas, each carrying its own anyOf list
        self::assertCount(2, $overrides['allOf']);
        self::assertSame([$resolvedA, $resolvedB], $overrides['allOf'][0]->anyOf);
        self::assertSame([$siblingC, $siblingD], $overrides['allOf'][1]->anyOf);
    }

    #[Test]
    public function merge_one_of_wraps_in_all_of_when_both_set(): void
    {
        $resolvedA = new Schema(type: 'string');
        $siblingC = new Schema(type: 'boolean');
        $resolved = new Schema(oneOf: [$resolvedA]);
        $sibling = new Schema(oneOf: [$siblingC]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['oneOf']);
        self::assertCount(2, $overrides['allOf']);
        self::assertSame([$resolvedA], $overrides['allOf'][0]->oneOf);
        self::assertSame([$siblingC], $overrides['allOf'][1]->oneOf);
    }

    #[Test]
    public function merge_composition_wrap_extends_existing_all_of(): void
    {
        $resolvedAllOfEntry = new Schema(type: 'string');
        $siblingAllOfEntry = new Schema(type: 'integer');
        $resolvedAnyOfA = new Schema(type: 'boolean');
        $siblingAnyOfC = new Schema(type: 'null');

        $resolved = new Schema(allOf: [$resolvedAllOfEntry], anyOf: [$resolvedAnyOfA]);
        $sibling = new Schema(allOf: [$siblingAllOfEntry], anyOf: [$siblingAnyOfC]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['anyOf']);
        self::assertCount(4, $overrides['allOf']);
        self::assertSame($resolvedAllOfEntry, $overrides['allOf'][0]);
        self::assertSame($siblingAllOfEntry, $overrides['allOf'][1]);
        self::assertSame([$resolvedAnyOfA], $overrides['allOf'][2]->anyOf);
        self::assertSame([$siblingAnyOfC], $overrides['allOf'][3]->anyOf);
    }

    #[Test]
    public function merge_composition_field_passes_through_when_only_resolved_set(): void
    {
        $resolvedA = new Schema(type: 'string');
        $resolved = new Schema(anyOf: [$resolvedA]);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['allOf']);
        self::assertSame([$resolvedA], $overrides['anyOf']);
    }

    #[Test]
    public function merge_composition_field_passes_through_when_only_sibling_set(): void
    {
        $resolved = new Schema(type: 'string');
        $siblingC = new Schema(type: 'boolean');
        $sibling = new Schema(oneOf: [$siblingC]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertNull($overrides['allOf']);
        self::assertSame([$siblingC], $overrides['oneOf']);
    }

    #[Test]
    public function merge_if_then_else_passes_through_when_only_one_side_set(): void
    {
        $ifSchema = new Schema(type: 'string');
        $thenSchema = new Schema(type: 'integer');
        $resolved = new Schema(if: $ifSchema, then: $thenSchema);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame($ifSchema, $overrides['if']);
        self::assertSame($thenSchema, $overrides['then']);
        self::assertNull($overrides['else']);
        self::assertNull($overrides['allOf']);
    }

    #[Test]
    public function merge_if_then_else_wraps_when_both_sides_set(): void
    {
        $resolvedIf = new Schema(type: 'string');
        $resolvedThen = new Schema(type: 'integer');
        $siblingIf = new Schema(type: 'boolean');
        $siblingThen = new Schema(type: 'null');

        $resolved = new Schema(if: $resolvedIf, then: $resolvedThen);
        $sibling = new Schema(if: $siblingIf, then: $siblingThen);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        // When both sides have if/then/else, fields are cleared and contributions go to allOf
        self::assertNull($overrides['if']);
        self::assertNull($overrides['then']);
        self::assertNull($overrides['else']);
        self::assertCount(2, $overrides['allOf']);
        self::assertSame($resolvedIf, $overrides['allOf'][0]->if);
        self::assertSame($resolvedThen, $overrides['allOf'][0]->then);
        self::assertSame($siblingIf, $overrides['allOf'][1]->if);
        self::assertSame($siblingThen, $overrides['allOf'][1]->then);
    }

    #[Test]
    public function merge_properties_concatenates_when_both_set(): void
    {
        $resolvedProp = new Schema(type: 'string');
        $siblingProp = new Schema(type: 'integer');
        $resolved = new Schema(properties: ['name' => $resolvedProp]);
        $sibling = new Schema(properties: ['age' => $siblingProp]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(
            ['name' => $resolvedProp, 'age' => $siblingProp],
            $overrides['properties'],
        );
    }

    #[Test]
    public function merge_pattern_properties_concatenates(): void
    {
        $resolvedProp = new Schema(type: 'string');
        $siblingProp = new Schema(type: 'integer');
        $resolved = new Schema(patternProperties: ['^a' => $resolvedProp]);
        $sibling = new Schema(patternProperties: ['^b' => $siblingProp]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(
            ['^a' => $resolvedProp, '^b' => $siblingProp],
            $overrides['patternProperties'],
        );
    }

    #[Test]
    public function merge_dependent_schemas_concatenates(): void
    {
        $resolvedDep = new Schema(type: 'string');
        $siblingDep = new Schema(type: 'integer');
        $resolved = new Schema(dependentSchemas: ['x' => $resolvedDep]);
        $sibling = new Schema(dependentSchemas: ['y' => $siblingDep]);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        self::assertSame(
            ['x' => $resolvedDep, 'y' => $siblingDep],
            $overrides['dependentSchemas'],
        );
    }

    #[Test]
    public function merge_scalar_additions_appended_to_all_of_when_types_conflict(): void
    {
        // resolved has type=string, sibling has type=integer → unmergeable types
        // plus resolved has pattern=^a, sibling pattern=^b → unmergeable patterns
        // All four should appear as separate allOf entries
        $resolved = new Schema(type: 'string', format: 'date-time', pattern: '^a', multipleOf: 2.0);
        $sibling = new Schema(type: 'integer', format: 'uuid', pattern: '^b', multipleOf: 3.0);

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        $allOf = $overrides['allOf'];
        self::assertNotNull($allOf);
        // 6 contributions: 2 type + 2 format + 2 multipleOf + 2 pattern = 8 (multipleOf+pattern via scalarMerger)
        self::assertCount(8, $allOf);
    }

    #[Test]
    public function merge_returns_overrides_for_all_composition_fields(): void
    {
        $resolved = new Schema(allOf: [new Schema(type: 'string')]);
        $sibling = new Schema();

        $overrides = $this->merger->merge(new SiblingMergeContext($resolved, $sibling));

        $expectedKeys = [
            'allOf', 'anyOf', 'oneOf',
            'if', 'then', 'else',
            'properties', 'patternProperties', 'dependentSchemas',
        ];
        foreach ($expectedKeys as $key) {
            self::assertArrayHasKey($key, $overrides, "Field {$key} missing from composition overrides");
        }
    }
}
