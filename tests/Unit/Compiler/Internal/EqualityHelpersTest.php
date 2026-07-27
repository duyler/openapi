<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\EqualityHelpers;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Golden-master + anti-test coverage for the EqualityHelpers collaborator.
 *
 * The emitted code mirrors `Duyler\OpenApi\Validator\Schema\JsonEquals`
 * byte-for-byte: JSON Schema 2020-12 §4.2.2 instance equality, IEEE 754
 * boundary `9007199254740992` (2^53) above which mixed int/float
 * comparisons are rejected as unequal, unordered object keys, and bool
 * distinct from int.
 */
final class EqualityHelpersTest extends TestCase
{
    private EqualityHelpers $helpers;

    protected function setUp(): void
    {
        $this->helpers = new EqualityHelpers();
    }

    #[Test]
    public function render_json_equals_inline_emits_method_signature(): void
    {
        $code = $this->helpers->renderJsonEqualsInline();

        self::assertStringContainsString('private function jsonEquals(mixed $a, mixed $b): bool', $code);
        self::assertStringContainsString('private function arraysEqual(array $a, array $b): bool', $code);
    }

    #[Test]
    public function render_json_equals_inline_preserves_int_float_equivalence_branch(): void
    {
        // JSON Schema 2020-12 §4.2.2: 1 == 1.0. The emitted code must
        // contain the int/float union branch that downcasts to float
        // comparison rather than using strict ===.
        $code = $this->helpers->renderJsonEqualsInline();

        self::assertStringContainsString('(is_int($a) || is_float($a))', $code);
        self::assertStringContainsString('(is_int($b) || is_float($b))', $code);
        self::assertStringContainsString('is_int($a) && is_int($b)', $code);
        self::assertStringContainsString('return (float) $a === (float) $b;', $code);
    }

    #[Test]
    public function render_json_equals_inline_preserves_bool_distinct_from_int_branch(): void
    {
        // JSON Schema 2020-12 §4.2.2: bool is distinct from int (true !=
        // 1). The emitted code must short-circuit on bool operands before
        // reaching the numeric branch.
        $code = $this->helpers->renderJsonEqualsInline();

        self::assertStringContainsString('if (is_bool($a) || is_bool($b))', $code);
        self::assertStringContainsString('return $a === $b;', $code);
    }

    #[Test]
    public function render_json_equals_inline_preserves_unordered_object_keys(): void
    {
        // {a:1,b:2} == {b:2,a:1}. The arraysEqual helper walks
        // array_keys($a) and looks up each key in $b (not by index), so
        // key insertion order does not affect equality.
        $code = $this->helpers->renderJsonEqualsInline();

        self::assertStringContainsString('foreach (array_keys($a) as $__key)', $code);
        self::assertStringContainsString('if (!array_key_exists($__key, $b))', $code);
        self::assertStringContainsString('if (!$this->jsonEquals($a[$__key], $b[$__key]))', $code);
    }

    #[Test]
    public function render_json_equals_inline_emits_list_short_circuit_for_lists(): void
    {
        // [1,2,3] uses the positional fast-path (array_is_list both sides).
        $code = $this->helpers->renderJsonEqualsInline();

        self::assertStringContainsString('array_is_list($a) && array_is_list($b)', $code);
        self::assertStringContainsString('for ($i = 0, $n = count($a); $i < $n; ++$i)', $code);
    }

    #[Test]
    public function render_json_equals_inline_preserves_ieee_754_boundary_literal(): void
    {
        // R3-CORRECTNESS-013: mixed int/float comparisons above the 2^53
        // IEEE 754 boundary are rejected as unequal. The literal
        // `9007199254740992` must appear verbatim in both branches; any
        // change (e.g. to PHP_INT_MAX or a different constant) would
        // silently break the boundary semantics.
        $code = $this->helpers->renderJsonEqualsInline();

        self::assertStringContainsString('9007199254740992', $code);
        self::assertStringContainsString('is_int($a) && abs($a) > 9007199254740992', $code);
        self::assertStringContainsString('is_int($b) && abs($b) > 9007199254740992', $code);
        self::assertSame(
            2,
            substr_count($code, '9007199254740992'),
            'IEEE 754 boundary literal must appear exactly twice (one per operand branch).',
        );
    }

    #[Test]
    public function render_canonical_key_inline_emits_method_signature(): void
    {
        $code = $this->helpers->renderCanonicalKeyInline();

        self::assertStringContainsString('private function canonicalJsonKey(mixed $value): string', $code);
        self::assertStringContainsString('private function canonicalizeArrayKeys(array $value): array', $code);
    }

    #[Test]
    public function render_canonical_key_inline_emits_whole_float_to_int_canonicalisation(): void
    {
        // canonicalJsonKey must downcast whole-float operands (1.0 -> 1)
        // before hashing so [1, 1.0] is detected as a duplicate in
        // uniqueItems. Without this downcast [1] and [1.0] would hash
        // differently and uniqueItems would silently miss duplicates.
        $code = $this->helpers->renderCanonicalKeyInline();

        self::assertStringContainsString('if (is_float($value) && (float) (int) $value === $value)', $code);
        self::assertStringContainsString('$value = (int) $value;', $code);
    }

    #[Test]
    public function render_canonical_key_inline_emits_ksort_for_unordered_object_keys(): void
    {
        // {a:1,b:2} and {b:2,a:1} must hash identically. ksort before
        // json_encode ensures insertion-order-independent canonicalisation.
        $code = $this->helpers->renderCanonicalKeyInline();

        self::assertStringContainsString('ksort($value);', $code);
    }

    #[Test]
    public function render_canonical_key_inline_emits_recursive_canonicalisation(): void
    {
        $code = $this->helpers->renderCanonicalKeyInline();

        self::assertStringContainsString('$value[$k] = $this->canonicalizeArrayKeys($v);', $code);
        self::assertStringContainsString('json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)', $code);
    }

    #[Test]
    public function render_json_equals_inline_byte_identical_to_pinned_golden_master(): void
    {
        // Pinned byte-for-byte: any change to the emitted codegen for
        // jsonEquals/arraysEqual breaks this test. The compiled validator
        // contract (R4-CORRECTNESS-013) requires byte-identity so cached
        // compiled validators remain valid across compiler versions.
        $code = $this->helpers->renderJsonEqualsInline();

        $expected = <<<'PHP'
    private function jsonEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return $a === $b;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            if (is_int($a) && is_int($b)) {
                return $a === $b;
            }
            if (is_int($a) && abs($a) > 9007199254740992) {
                return false;
            }
            if (is_int($b) && abs($b) > 9007199254740992) {
                return false;
            }
            return (float) $a === (float) $b;
        }
        if (is_array($a) && is_array($b)) {
            return $this->arraysEqual($a, $b);
        }
        return $a === $b;
    }

    private function arraysEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        if (array_is_list($a) && array_is_list($b)) {
            for ($i = 0, $n = count($a); $i < $n; ++$i) {
                if (!$this->jsonEquals($a[$i], $b[$i])) {
                    return false;
                }
            }
            return true;
        }
        foreach (array_keys($a) as $__key) {
            if (!array_key_exists($__key, $b)) {
                return false;
            }
            if (!$this->jsonEquals($a[$__key], $b[$__key])) {
                return false;
            }
        }
        return true;
    }

PHP;

        self::assertSame($expected, $code);
    }

    #[Test]
    public function render_canonical_key_inline_byte_identical_to_pinned_golden_master(): void
    {
        $code = $this->helpers->renderCanonicalKeyInline();

        $expected = <<<'PHP'
    private function canonicalJsonKey(mixed $value): string
    {
        if (is_float($value) && (float) (int) $value === $value) {
            $value = (int) $value;
        }
        if (is_array($value)) {
            $value = $this->canonicalizeArrayKeys($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function canonicalizeArrayKeys(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->canonicalizeArrayKeys($v);
            } elseif (is_float($v) && (float) (int) $v === $v) {
                $value[$k] = (int) $v;
            }
        }
        return $value;
    }

PHP;

        self::assertSame($expected, $code);
    }

    #[Test]
    public function is_required_for_returns_false_for_empty_schema(): void
    {
        // Empty schema (no enum/const/uniqueItems) -> helpers not emitted.
        self::assertFalse($this->helpers->isRequiredFor(new Schema()));
    }

    #[Test]
    public function is_required_for_returns_true_when_schema_has_enum(): void
    {
        self::assertTrue($this->helpers->isRequiredFor(new Schema(enum: ['a', 'b'])));
    }

    #[Test]
    public function is_required_for_returns_true_when_schema_has_const(): void
    {
        self::assertTrue($this->helpers->isRequiredFor(new Schema(const: 'fixed', hasConst: true)));
    }

    #[Test]
    public function is_required_for_returns_true_when_schema_has_unique_items(): void
    {
        self::assertTrue($this->helpers->isRequiredFor(new Schema(uniqueItems: true)));
    }

    #[Test]
    public function is_required_for_walks_nested_properties(): void
    {
        // The equality helpers are needed when an enum/const/uniqueItems
        // keyword appears anywhere in the schema tree, not just at the
        // root. The compiled class footer emits them once for the whole
        // tree, so a nested property using enum still requires the
        // jsonEquals helper to be present in the generated class.
        $schema = new Schema(
            type: 'object',
            properties: [
                'status' => new Schema(type: 'string', enum: ['active', 'archived']),
            ],
        );

        self::assertTrue($this->helpers->isRequiredFor($schema));
    }

    #[Test]
    public function is_required_for_walks_nested_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(enum: [1, 2, 3]),
        );

        self::assertTrue($this->helpers->isRequiredFor($schema));
    }

    #[Test]
    public function is_required_for_returns_false_when_no_equality_keywords_anywhere(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(type: 'string', minLength: 1),
                'age' => new Schema(type: 'integer', minimum: 0),
            ],
            required: ['name'],
        );

        self::assertFalse($this->helpers->isRequiredFor($schema));
    }

    #[Test]
    public function is_required_for_returns_true_for_unique_items_nested_in_property(): void
    {
        // uniqueItems requires canonicalJsonKey helper even when the
        // uniqueItems keyword is nested inside a property subschema.
        $schema = new Schema(
            type: 'object',
            properties: [
                'tags' => new Schema(type: 'array', uniqueItems: true),
            ],
        );

        self::assertTrue($this->helpers->isRequiredFor($schema));
    }
}
