<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\ArrayLengthValidator;
use Duyler\OpenApi\Validator\SchemaValidator\ValidatorDependencies;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use JsonException;

use function range;
use function str_replace;
use function substr;
use function substr_count;
use function assert;
use function sprintf;

final class ValidatorCompilerRuntimeEquivalenceTest extends TestCase
{
    /**
     * R3-CORRECTNESS-001: JSON Schema 2020-12 §4.2.2 numeric equality says
     * int 1 and float 1.0 are equal. Runtime accepts; the compiled
     * validator must also accept after the inline-jsonEquals fix.
     */
    #[Test]
    public function compiled_const_accepts_float_equal_to_int_const(): void
    {
        $validator = $this->compileValidator(
            new Schema(type: 'integer', const: 1, hasConst: true),
            'ConstAcceptsFloatEqualToIntValidator',
        );

        $validator->validate(1.0);

        self::assertTrue(true, 'Compiled validator accepts 1.0 for const:1 per §4.2.2 numeric equality.');
    }

    /**
     * R3-CORRECTNESS-001 (negative): a genuinely distinct value must
     * still be rejected. Guards against the opposite regression where
     * the jsonEquals helper would always return true.
     */
    #[Test]
    public function compiled_const_rejects_distinct_value(): void
    {
        $validator = $this->compileValidator(
            new Schema(type: 'integer', const: 1, hasConst: true),
            'ConstRejectsDistinctValueValidator',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be const');

        $validator->validate(2);
    }

    /**
     * R3-CORRECTNESS-001 (object key-order): JSON Schema §4.2.2 says
     * object keys are unordered. Runtime accepts reordered-key objects
     * for const; the compiled validator must also accept.
     */
    #[Test]
    public function compiled_const_accepts_unordered_assoc_array(): void
    {
        $validator = $this->compileValidator(
            new Schema(
                type: 'object',
                const: ['a' => 1, 'b' => 2],
                hasConst: true,
            ),
            'ConstAcceptsUnorderedAssocValidator',
        );

        $validator->validate(['b' => 2, 'a' => 1]);

        self::assertTrue(true, 'Compiled validator accepts reordered-key assoc array for const per §4.2.2 object equality.');
    }

    /**
     * R3-CORRECTNESS-001 (int64 boundary): mixed int/float comparisons
     * above 2^53 cannot be decided accurately (the int loses precision
     * when cast to float), so equality is rejected as false. const:2^53+1
     * (int) vs 2^53 (float) must throw.
     */
    #[Test]
    public function compiled_const_rejects_above_int64_float_boundary(): void
    {
        $validator = $this->compileValidator(
            new Schema(
                type: 'integer',
                const: 9007199254740993,
                hasConst: true,
            ),
            'ConstRejectsAboveBoundaryValidator',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be const');

        $validator->validate(9007199254740992.0);
    }

    /**
     * R3-CORRECTNESS-002: enum with float values must accept the int
     * form (1 == 1.0 per §4.2.2). Runtime accepts; compiled validator
     * must also accept.
     */
    #[Test]
    public function compiled_enum_accepts_int_for_float_enum(): void
    {
        $validator = $this->compileValidator(
            new Schema(type: 'number', enum: [1.0, 2.0, 3.0]),
            'EnumAcceptsIntForFloatValidator',
        );

        $validator->validate(1);

        self::assertTrue(true, 'Compiled validator accepts int 1 for float-enum [1.0,2.0,3.0] per §4.2.2 numeric equality.');
    }

    /**
     * R3-CORRECTNESS-002 (negative): a value not in enum must throw
     * RuntimeException with the inline-values message.
     */
    #[Test]
    public function compiled_enum_rejects_value_not_in_enum(): void
    {
        $validator = $this->compileValidator(
            new Schema(type: 'string', enum: ['active', 'inactive']),
            'EnumRejectsValueNotInEnumValidator',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be one of');

        $validator->validate('pending');
    }

    /**
     * R3-CORRECTNESS-003: schema `{type:array, uniqueItems:true}` with
     * input `[1, 1.0]` must throw — runtime rejects (1 == 1.0 duplicate),
     * compiled must also reject after the canonicalJsonKey fix.
     */
    #[Test]
    public function compiled_unique_items_rejects_int_and_float_pair(): void
    {
        $validator = $this->compileValidator(
            new Schema(
                type: 'array',
                items: new Schema(type: 'number'),
                uniqueItems: true,
            ),
            'UniqueItemsRejectsIntFloatValidator',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array items must be unique');

        $validator->validate([1, 1.0]);
    }

    /**
     * R3-CORRECTNESS-003 (object key-order via canonicalization):
     * input `[['a'=>1,'b'=>2], ['b'=>2,'a'=>1]]` represents two
     * JSON-equal objects per §4.2.2 (unordered keys). Runtime rejects
     * as duplicate; compiled must also reject after the ksort-based
     * canonicalization.
     */
    #[Test]
    public function compiled_unique_items_rejects_duplicate_objects_with_reordered_keys(): void
    {
        $validator = $this->compileValidator(
            new Schema(
                type: 'array',
                items: new Schema(type: 'object'),
                uniqueItems: true,
            ),
            'UniqueItemsRejectsReorderedKeysValidator',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array items must be unique');

        $validator->validate([['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]]);
    }

    /**
     * R3-PERF-001 partial: cap of 100000 unique entries prevents O(n)
     * hash-table growth from being weaponised into a memory-exhaustion
     * DoS. An input with 100001 distinct entries must throw
     * RuntimeException('Too many items for unique check').
     */
    #[Test]
    public function compiled_unique_items_rejects_when_count_exceeds_cap(): void
    {
        $validator = $this->compileValidator(
            new Schema(
                type: 'array',
                items: new Schema(type: 'integer'),
                uniqueItems: true,
            ),
            'UniqueItemsRejectsCapValidator',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Too many items for unique check');

        $validator->validate(range(1, 100001));
    }

    /**
     * R3-CORRECTNESS-013: JsonException (extends Exception, NOT
     * RuntimeException) leaked out of the compiled validator before the
     * try/catch wrapper. A resource value triggers json_encode failure;
     * the wrapper must convert it to RuntimeException so callers that
     * `catch (\RuntimeException $e)` see the failure. Verified by
     * catching `\Throwable` and asserting the caught instance is a
     * RuntimeException and not a JsonException.
     */
    #[Test]
    public function compiled_unique_items_converts_json_exception_to_runtime_exception(): void
    {
        $validator = $this->compileValidator(
            new Schema(
                type: 'array',
                items: new Schema(type: 'integer'),
                uniqueItems: true,
            ),
            'UniqueItemsConvertsJsonExceptionValidator',
        );

        $resource = fopen('php://memory', 'r');
        assert(false !== $resource);

        try {
            $caught = null;

            try {
                $validator->validate([$resource]);
            } catch (Throwable $e) {
                $caught = $e;
            }

            self::assertNotNull($caught, 'Expected the validator to throw on a non-encodable resource value.');
            self::assertInstanceOf(RuntimeException::class, $caught);
            self::assertStringContainsString('Failed to encode value for uniqueness check', $caught->getMessage());
            self::assertNotInstanceOf(JsonException::class, $caught);
        } finally {
            fclose($resource);
        }
    }

    /**
     * Helpers must not pollute the generated class when the schema does
     * not use const / enum / uniqueItems. Guards against unconditional
     * emission.
     */
    #[Test]
    public function compiled_helpers_emitted_only_when_needed(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'integer');

        $code = $compiler->compile($schema, 'HelpersOnlyWhenNeededValidator');

        self::assertStringNotContainsString('function jsonEquals', $code);
        self::assertStringNotContainsString('function canonicalJsonKey', $code);
        self::assertStringNotContainsString('function arraysEqual', $code);
        self::assertStringNotContainsString('function canonicalizeArrayKeys', $code);
    }

    /**
     * Deduplication: a schema with const + enum + uniqueItems must emit
     * the jsonEquals helper exactly once. Guards against the regression
     * where multiple keyword generators each emit their own copy.
     */
    #[Test]
    public function compiled_helpers_emitted_once_for_combined_schema(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: 'integer'),
            uniqueItems: true,
            const: [1, 2, 3],
            hasConst: true,
            enum: [[1, 2, 3], [4, 5, 6]],
        );

        $code = $compiler->compile($schema, 'HelpersOnceCombinedValidator');

        self::assertSame(1, substr_count($code, 'function jsonEquals'));
        self::assertSame(1, substr_count($code, 'function canonicalJsonKey'));
        self::assertSame(1, substr_count($code, 'function arraysEqual'));
        self::assertSame(1, substr_count($code, 'function canonicalizeArrayKeys'));
    }

    /**
     * Helpers are emitted when `uniqueItems: true` appears inside a nested
     * object property, not at the top level. Guards the recursive walker
     * `schemaHasUniqueItemsInItemsChain`.
     *
     * Known limitation: helpers are emitted via the walker, but the actual
     * uniqueItems CHECK is NOT generated for nested object properties —
     * `generatePropertyValidation` only emits type checks for properties,
     * not array/uniqueItems checks. The helpers are therefore emitted as
     * dead code in this case. This is a known limitation of the compiler's
     * property-validation pipeline, tracked for a follow-up task.
     */
    #[Test]
    public function compiled_helpers_emitted_for_nested_unique_items(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                    uniqueItems: true,
                ),
            ],
        );

        $code = $compiler->compile($schema, 'HelpersForNestedUniqueItemsValidator');

        self::assertStringContainsString('function jsonEquals', $code);
        self::assertStringContainsString('function canonicalJsonKey', $code);
    }

    /**
     * Cross-validator equivalence: a parallel run of the runtime
     * ArrayLengthValidator and the compiled validator on edge cases
     * (int64-float, assoc-arrays with reordered keys, distinct JSON
     * types) must agree on pass/fail for the uniqueItems keyword.
     */
    #[Test]
    public function compiled_unique_items_runtime_equivalence_for_edge_cases(): void
    {
        $runtimeValidator = new ArrayLengthValidator(
            new ValidatorDependencies(pool: new ValidatorPool(), formatRegistry: BuiltinFormats::create()),
        );
        $schema = new Schema(
            type: 'array',
            items: new Schema(type: ['integer', 'number', 'string', 'boolean', 'object', 'array']),
            uniqueItems: true,
        );
        $compiledValidator = $this->compileValidator($schema, 'UniqueItemsEquivalenceValidator');

        $cases = [
            'int_float_pair' => [1, 1.0],            // duplicate per §4.2.2
            'distinct_json_types' => [1, '1', true],  // distinct
            'reordered_assoc' => [['a' => 1, 'b' => 2], ['b' => 2, 'a' => 1]], // duplicate
            'identical_lists' => [[1, 2], [1, 2]],    // duplicate (order matters for lists)
            'distinct_lists_order' => [[1, 2], [2, 1]], // distinct (list order matters)
            'whole_float_duplicate' => [3.0, 3],      // duplicate
            'bool_int_distinct' => [true, 1],         // distinct per §4.2.2 (bool ≠ int)
            'empty_array' => [],                       // trivially unique
        ];

        foreach ($cases as $label => $payload) {
            $runtimeThrew = false;
            $compiledThrew = false;

            try {
                $runtimeValidator->validate(
                    $payload,
                    $schema,
                );
            } catch (Throwable) {
                $runtimeThrew = true;
            }

            try {
                $compiledValidator->validate($payload);
            } catch (Throwable) {
                $compiledThrew = true;
            }

            self::assertSame(
                $runtimeThrew,
                $compiledThrew,
                sprintf('Case "%s": runtime %s but compiled %s.', $label, $runtimeThrew ? 'threw' : 'passed', $compiledThrew ? 'threw' : 'passed'),
            );
        }
    }

    private function compileValidator(Schema $schema, string $shortName): object
    {
        $code = new ValidatorCompiler()->compile($schema, $shortName);

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));

        /**
         * Generated code is produced by ValidatorCompiler for trusted schemas
         * (OpenAPI documents under our control) and is parsed during tests via
         * token_get_all elsewhere. This eval is the documented contract for
         * exercising compiled validators (see ValidatorCompilerTest,
         * ValidatorCompilerPatternTest).
         */
        eval($evalCode);

        return new $shortName();
    }
}
