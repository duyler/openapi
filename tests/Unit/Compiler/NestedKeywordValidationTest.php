<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function bin2hex;
use function random_bytes;
use function str_replace;
use function substr;
use function sprintf;

/**
 * Regression tests for R4-CORRECTNESS-004 (nested keyword coverage) and
 * R4-CORRECTNESS-013 (instance equality for enum inside items). Each
 * test compiles a Schema whose nested property/item declares one of the
 * supported keywords and asserts that the generated validator enforces
 * the keyword at runtime. Anti-tests: reverting the
 * `generateConstraintsForSchema` unification would make these tests
 * fail because the nested keyword would be silently ignored.
 */
final class NestedKeywordValidationTest extends TestCase
{
    /**
     * R4-CORRECTNESS-004 anti-test: pattern declared on a nested object
     * property must be enforced by the compiled validator. Before the
     * fix, only `is_string(...)` was emitted for nested string
     * properties and the pattern was silently ignored.
     */
    #[Test]
    public function nested_property_pattern_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'email' => new Schema(
                    type: 'string',
                    pattern: '^[^@]+@[^@]+$',
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pattern validation failed');

        $validator->validate(['email' => 'not-an-email']);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: maxLength on a nested object property
     * must be enforced. Before the fix, only `is_string(...)` was
     * emitted and maxLength was silently ignored.
     */
    #[Test]
    public function nested_property_max_length_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'name' => new Schema(
                    type: 'string',
                    maxLength: 10,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('String length validation failed');

        $validator->validate(['name' => str_repeat('a', 100)]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: minLength on a nested object property
     * must be enforced.
     */
    #[Test]
    public function nested_property_min_length_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'code' => new Schema(
                    type: 'string',
                    minLength: 5,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);

        $validator->validate(['code' => 'ab']);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: minimum on a nested object property
     * must be enforced. Before the fix, only `is_int(...)` was emitted.
     */
    #[Test]
    public function nested_property_minimum_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'age' => new Schema(
                    type: 'integer',
                    minimum: 0,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Number range validation failed');

        $validator->validate(['age' => -5]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: maximum on a nested object property
     * must be enforced.
     */
    #[Test]
    public function nested_property_maximum_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'percent' => new Schema(
                    type: 'integer',
                    maximum: 100,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);

        $validator->validate(['percent' => 250]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: exclusiveMinimum / exclusiveMaximum
     * on a nested object property must be enforced.
     */
    #[Test]
    public function nested_property_exclusive_minimum_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'score' => new Schema(
                    type: 'integer',
                    exclusiveMinimum: 0,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);

        $validator->validate(['score' => 0]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: multipleOf on a nested object
     * property must be enforced via the same int-modulus / float-quotient
     * logic as the top-level path.
     */
    #[Test]
    public function nested_property_multiple_of_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'quantity' => new Schema(
                    type: 'integer',
                    multipleOf: 5,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be a multiple of 5');

        $validator->validate(['quantity' => 7]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: enum on a nested object property
     * must use the inlined JsonEquals helper so JSON Schema 2020-12
     * §4.2.2 instance equality holds (1 == 1.0).
     */
    #[Test]
    public function nested_property_enum_check_uses_instance_equality(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'level' => new Schema(
                    type: 'integer',
                    enum: [1, 2, 3],
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectNoException(function () use ($validator): void {
            $validator->validate(['level' => 1.0]);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be one of');

        $validator->validate(['level' => 9]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: const on a nested object property
     * must be enforced via the inlined JsonEquals helper.
     */
    #[Test]
    public function nested_property_const_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'kind' => new Schema(
                    type: 'string',
                    const: 'fixed',
                    hasConst: true,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be const');

        $validator->validate(['kind' => 'other']);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: required declared inside a nested
     * object property must be enforced by the compiled validator.
     */
    #[Test]
    public function nested_property_required_check_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(
                    type: 'object',
                    properties: [
                        'city' => new Schema(type: 'string'),
                    ],
                    required: ['city'],
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required property missing');

        $validator->validate(['address' => []]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: additionalProperties: false declared
     * inside a nested object property must be enforced.
     */
    #[Test]
    public function nested_property_additional_properties_false_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'address' => new Schema(
                    type: 'object',
                    properties: [
                        'city' => new Schema(type: 'string'),
                    ],
                    additionalProperties: false,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Additional property not allowed');

        $validator->validate(['address' => ['city' => 'NYC', 'zip' => '10001']]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: minItems, maxItems, uniqueItems on a
     * nested array property must be enforced. Before the fix, these
     * were silently ignored for nested properties (the helper methods
     * were emitted as dead code).
     */
    #[Test]
    public function nested_property_array_constraints_are_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                    minItems: 1,
                    maxItems: 3,
                    uniqueItems: true,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);

        $validator->validate(['tags' => []]);
    }

    #[Test]
    public function nested_property_max_items_is_enforced(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'string'),
                    maxItems: 2,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array too long');

        $validator->validate(['tags' => ['a', 'b', 'c', 'd']]);
    }

    #[Test]
    public function nested_property_unique_items_is_enforced(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'tags' => new Schema(
                    type: 'array',
                    items: new Schema(type: 'integer'),
                    uniqueItems: true,
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array items must be unique');

        $validator->validate(['tags' => [1, 1.0]]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: constraints declared on array ITEMS
     * (not just on the array itself) must be enforced. Before the fix,
     * `generateItemValidation` only emitted `type` (and strict
     * `in_array` for enum), ignoring minLength, maximum, pattern, etc.
     */
    #[Test]
    public function nested_items_minimum_is_emitted(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'id' => new Schema(
                        type: 'integer',
                        minimum: 1,
                    ),
                ],
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Number range validation failed');

        $validator->validate([['id' => 0], ['id' => 2]]);
    }

    #[Test]
    public function nested_items_pattern_is_emitted(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'string',
                pattern: '^[a-z]+$',
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pattern validation failed');

        $validator->validate(['lowercase', 'MIXED']);
    }

    #[Test]
    public function nested_items_max_length_is_emitted(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'string',
                maxLength: 5,
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('String length validation failed');

        $validator->validate(['ok', 'too_long_string']);
    }

    #[Test]
    public function nested_items_multiple_of_is_emitted(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'integer',
                multipleOf: 3,
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be a multiple of 3');

        $validator->validate([3, 6, 7]);
    }

    #[Test]
    public function nested_items_required_is_emitted(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                properties: [
                    'id' => new Schema(type: 'integer'),
                ],
                required: ['id'],
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Required property missing');

        $validator->validate([['name' => 'no id']]);
    }

    /**
     * R4-CORRECTNESS-013 anti-test: enum declared inside array ITEMS
     * must use the inlined JsonEquals helper so JSON Schema 2020-12
     * §4.2.2 instance equality holds (`1.0` is equal to `1`). Before
     * the fix, strict `in_array(..., true)` rejected `1.0` against
     * enum `[1, 2, 3]`.
     */
    #[Test]
    public function nested_items_enum_uses_instance_equality(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'integer',
                enum: [1, 2, 3],
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectNoException(function () use ($validator): void {
            $validator->validate([1.0, 2.0]);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Value must be one of');

        $validator->validate([9]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: nested arrays of arrays. The inner
     * `foreach` must use a depth-suffixed loop variable so the outer
     * iteration value is preserved. Before the fix, items-in-items was
     * silently ignored at every nested level.
     */
    #[Test]
    public function nested_array_of_arrays_validates_inner_items(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'array',
                items: new Schema(type: 'integer'),
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectNoException(function () use ($validator): void {
            $validator->validate([[1, 2], [3, 4]]);
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Type mismatch');

        $validator->validate([[1, 2], ['not-an-int']]);
    }

    #[Test]
    public function nested_array_of_arrays_validates_inner_minimum(): void
    {
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'array',
                items: new Schema(
                    type: 'integer',
                    minimum: 0,
                ),
            ),
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Number range validation failed');

        $validator->validate([[1, 2], [-1]]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: deeply nested object property (5+
     * levels) must emit constraints at every level, exercising the
     * recursion in `generateConstraintsForSchema`.
     */
    #[Test]
    public function deeply_nested_property_minimum_is_emitted(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'a' => new Schema(
                    type: 'object',
                    properties: [
                        'b' => new Schema(
                            type: 'object',
                            properties: [
                                'c' => new Schema(
                                    type: 'object',
                                    properties: [
                                        'd' => new Schema(
                                            type: 'object',
                                            properties: [
                                                'value' => new Schema(
                                                    type: 'integer',
                                                    minimum: 10,
                                                ),
                                            ],
                                        ),
                                    ],
                                ),
                            ],
                        ),
                    ],
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Number range validation failed');

        $validator->validate(['a' => ['b' => ['c' => ['d' => ['value' => 5]]]]]);
    }

    /**
     * R4-CORRECTNESS-004 anti-test: unsupported nested keyword in a
     * nested property must throw `UnsupportedKeywordException` at
     * compile time. Before the fix, `format` was silently ignored for
     * nested properties; the compiler emitted a validator that
     * accepted invalid data.
     */
    #[Test]
    public function nested_property_format_keyword_throws(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'email' => new Schema(
                    type: 'string',
                    format: 'email',
                ),
            ],
        );

        $caught = null;

        try {
            $compiler->compile($schema, 'NestedFormatThrowsValidator');
        } catch (UnsupportedKeywordException $e) {
            $caught = $e;
        }

        self::assertNotNull($caught);
        self::assertContains('format', $caught->keywords);
        self::assertStringContainsString('format', $caught->getMessage());
    }

    #[Test]
    public function nested_property_all_of_throws(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'thing' => new Schema(
                    type: 'object',
                    allOf: [new Schema(type: 'object')],
                ),
            ],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'NestedAllOfValidator');
    }

    #[Test]
    public function nested_items_one_of_throws(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'array',
            items: new Schema(
                type: 'object',
                oneOf: [new Schema(type: 'object')],
            ),
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'ItemsOneOfValidator');
    }

    #[Test]
    public function nested_property_pattern_properties_throws(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'thing' => new Schema(
                    type: 'object',
                    patternProperties: ['^S_' => new Schema(type: 'string')],
                ),
            ],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'NestedPatternPropertiesValidator');
    }

    #[Test]
    public function nested_property_prefix_items_throws(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'tuple' => new Schema(
                    type: 'array',
                    prefixItems: [new Schema(type: 'string')],
                ),
            ],
        );

        $this->expectException(UnsupportedKeywordException::class);

        $compiler->compile($schema, 'NestedPrefixItemsValidator');
    }

    /**
     * R4-CORRECTNESS-004 anti-test: keyword coverage symmetry. The
     * nested property check must emit the SAME constraint expression
     * as the top-level check (no parallel implementation drift).
     */
    #[Test]
    public function nested_property_pattern_uses_backtrack_limit_wrapper(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'token' => new Schema(
                    type: 'string',
                    pattern: '^[a-z]+$',
                ),
            ],
        );

        $code = $compiler->compile($schema, 'NestedBacktrackWrapperValidator');

        self::assertStringContainsString("ini_set('pcre.backtrack_limit'", $code);
        self::assertStringContainsString('restore_error_handler', $code);
    }

    #[Test]
    public function nested_property_multiple_of_uses_relative_epsilon(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(
            type: 'object',
            properties: [
                'rate' => new Schema(
                    type: 'number',
                    multipleOf: 0.5,
                ),
            ],
        );

        $code = $compiler->compile($schema, 'NestedEpsilonValidator');

        self::assertStringContainsString('$quotient = (float)', $code);
        self::assertStringContainsString('$rounded = round($quotient)', $code);
        self::assertStringContainsString('1.0E-9 * max(1.0, abs($quotient))', $code);
    }

    /**
     * Sanity check: a deeply-nested valid payload passes the compiled
     * validator without false-positive rejections, exercising the
     * recursion happy path.
     */
    #[Test]
    public function nested_valid_payload_passes_compiled_validator(): void
    {
        $schema = new Schema(
            type: 'object',
            properties: [
                'user' => new Schema(
                    type: 'object',
                    properties: [
                        'name' => new Schema(
                            type: 'string',
                            minLength: 1,
                            maxLength: 50,
                        ),
                        'age' => new Schema(
                            type: 'integer',
                            minimum: 0,
                            maximum: 150,
                        ),
                        'tags' => new Schema(
                            type: 'array',
                            items: new Schema(type: 'string'),
                            minItems: 0,
                            maxItems: 10,
                        ),
                    ],
                    required: ['name'],
                ),
            ],
        );

        $validator = $this->compileUniqueValidator($schema);

        $this->expectNoException(function () use ($validator): void {
            $validator->validate([
                'user' => [
                    'name' => 'Alice',
                    'age' => 30,
                    'tags' => ['admin', 'staff'],
                ],
            ]);
        });
    }

    private function compileUniqueValidator(Schema $schema): object
    {
        $shortName = 'NestedKeyword_' . bin2hex(random_bytes(6));
        $code = new ValidatorCompiler()->compile($schema, $shortName);

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));

        /**
         * Generated code is produced by ValidatorCompiler for trusted
         * schemas (OpenAPI documents under our control) and is parsed
         * during tests via token_get_all elsewhere. This eval is the
         * documented contract for exercising compiled validators (see
         * ValidatorCompilerTest).
         */
        eval($evalCode);

        return new $shortName();
    }

    /**
     * Inverse of `expectException`: asserts the callback does not
     * throw, failing the test with the thrown instance if it does.
     *
     * @param callable(): void $callback
     */
    private function expectNoException(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            self::fail(sprintf(
                'Expected no exception, but %s was thrown: %s',
                $e::class,
                $e->getMessage(),
            ));
        }

        self::assertTrue(true);
    }
}
