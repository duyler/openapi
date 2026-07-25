<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\FloatQuotientContext;
use Duyler\OpenApi\Compiler\Internal\MultipleOfContext;
use Duyler\OpenApi\Compiler\Internal\PatternCheck;
use Duyler\OpenApi\Compiler\Internal\ScalarConstraints;
use Duyler\OpenApi\Compiler\Internal\Utf16Length;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Golden-master + unit tests for the ScalarConstraints collaborator.
 *
 * Each test asserts a substring of the emitted codegen. The substrings
 * were captured from the pre-refactor ValidatorCompiler output (task 10a
 * extraction). The collaborator must keep emitting the same substrings
 * so the compiled validators behave identically to the baseline.
 */
final class ScalarConstraintsTest extends TestCase
{
    private ScalarConstraints $scalar;

    protected function setUp(): void
    {
        $this->scalar = new ScalarConstraints(new Utf16Length(), new PatternCheck());
    }

    #[Test]
    public function generate_emits_nothing_for_empty_schema(): void
    {
        $code = $this->scalar->generate(new Schema(), '$data');

        self::assertSame('', $code, 'Empty schema must produce zero codegen.');
    }

    #[Test]
    public function generate_emits_type_check_for_string(): void
    {
        $code = $this->scalar->generate(new Schema(type: 'string'), '$data');

        self::assertStringContainsString('is_string($data)', $code);
        self::assertStringContainsString("'Type mismatch: expected '", $code);
        self::assertStringContainsString('TypeFormatter::format($data)', $code);
    }

    #[Test]
    public function generate_emits_type_check_for_integer_with_whole_float_acceptance(): void
    {
        $code = $this->scalar->generate(new Schema(type: 'integer'), '$data');

        // JSON Schema 2020-12 §4.2.3: a number with zero fractional part is
        // an integer. The emitted check must accept 3.0 — `is_int || (is_float
        // && fmod === 0.0 && !inf && !nan)` — NOT just `is_int($data)`.
        self::assertStringContainsString('is_int($data)', $code);
        self::assertStringContainsString('is_float($data)', $code);
        self::assertStringContainsString('0.0 === fmod($data, 1.0)', $code);
        self::assertStringContainsString('!is_infinite($data)', $code);
        self::assertStringContainsString('!is_nan($data)', $code);
    }

    #[Test]
    public function generate_emits_type_check_for_number_as_int_or_float_union(): void
    {
        $code = $this->scalar->generate(new Schema(type: 'number'), '$data');

        self::assertStringContainsString('(is_float($data) || is_int($data))', $code);
    }

    #[Test]
    public function generate_emits_enum_check_with_json_equals_helper(): void
    {
        $code = $this->scalar->generate(new Schema(enum: ['cat', 'dog']), '$data');

        self::assertStringContainsString('$__matched = false;', $code);
        self::assertStringContainsString('foreach ([', $code);
        self::assertStringContainsString("'cat'", $code);
        self::assertStringContainsString("'dog'", $code);
        self::assertStringContainsString('$this->jsonEquals($__candidate, $data)', $code);
        self::assertStringContainsString("'Value must be one of: '", $code);
    }

    #[Test]
    public function generate_emits_const_check_with_json_equals_helper(): void
    {
        $code = $this->scalar->generate(new Schema(const: 'fixed', hasConst: true), '$data');

        self::assertStringContainsString("\$this->jsonEquals('fixed', \$data)", $code);
        // The error message is sprintf'd with the offending value.
        self::assertStringContainsString("sprintf('Value must be const: %s'", $code);
    }

    #[Test]
    public function generate_emits_string_length_check_with_utf16_byte_walk(): void
    {
        $code = $this->scalar->generate(new Schema(type: 'string', minLength: 1, maxLength: 100), '$data');

        // Delegates to Utf16Length: the byte-walking loop must be inlined.
        self::assertStringContainsString('$utf16Length = 0;', $code);
        self::assertStringContainsString('$utf16Bytes = strlen((string) $data);', $code);
        self::assertStringContainsString('$utf16Octet = ord($data[$utf16Pos]);', $code);
        self::assertStringContainsString('$utf16Pos += 4;', $code);
        self::assertStringContainsString('$utf16Length += 2;', $code);
        self::assertStringNotContainsString('mb_strlen', $code);

        // Range conditions follow the UTF-16 length computation.
        self::assertStringContainsString('$utf16Length < 1', $code);
        self::assertStringContainsString('$utf16Length > 100', $code);
        self::assertStringContainsString("'String length validation failed'", $code);
    }

    #[Test]
    public function generate_emits_number_range_check_with_each_bound(): void
    {
        $code = $this->scalar->generate(
            new Schema(
                type: 'number',
                minimum: 0,
                maximum: 1000,
                exclusiveMinimum: 10,
                exclusiveMaximum: 990,
            ),
            '$data',
        );

        // Each bound contributes a separate condition to the OR chain.
        self::assertStringContainsString('$data < 0.000000', $code);
        self::assertStringContainsString('$data > 1000.000000', $code);
        self::assertStringContainsString('$data <= 10.000000', $code);
        self::assertStringContainsString('$data >= 990.000000', $code);
        self::assertStringContainsString("'Number range validation failed'", $code);
    }

    #[Test]
    public function generate_emits_pattern_check_with_redos_defence(): void
    {
        $code = $this->scalar->generate(new Schema(type: 'string', pattern: '^[a-z]+$'), '$data');

        // Delegates to PatternCheck: the ReDoS defence (CWE-1333) must be
        // inlined byte-for-byte.
        self::assertStringContainsString("\$previous = ini_get('pcre.backtrack_limit');", $code);
        self::assertStringContainsString("ini_set('pcre.backtrack_limit', '10000');", $code);
        self::assertStringContainsString('try {', $code);
        self::assertStringContainsString('} finally {', $code);
        self::assertStringContainsString('restore_error_handler();', $code);
    }

    #[Test]
    public function generate_emits_integer_multiple_of_with_modulus_and_float_fallback(): void
    {
        // Integer multipleOf uses `%` for int input and falls back to the
        // quotient+relative-epsilon check for float input. The dispatch is
        // `if (is_int($data)) { ... } else { ... }`.
        $code = $this->scalar->generate(new Schema(type: 'number', multipleOf: 2), '$data');

        self::assertStringContainsString('if (is_int($data)) {', $code);
        self::assertStringContainsString('0 !== ($data % 2)', $code);
        self::assertStringContainsString('} else {', $code);
        // multipleOf is stored as float on Schema, so var_export emits '2.0'.
        self::assertStringContainsString("\$quotient = (float) \$data / 2.0;", $code);
        self::assertStringContainsString('$rounded = round($quotient);', $code);
        self::assertStringContainsString('abs($quotient - $rounded)', $code);
        self::assertStringContainsString("'Value must be a multiple of 2.0'", $code);
    }

    #[Test]
    public function generate_emits_float_quotient_check_for_fractional_multiple_of(): void
    {
        // Fractional multipleOf (e.g. 0.1) skips the integer branch
        // entirely and emits only the quotient+relative-epsilon check.
        // The integer-branch substring `is_int($data)` still appears in
        // the type: 'number' check, so we look for the modulus-specific
        // substring instead.
        $code = $this->scalar->generate(new Schema(type: 'number', multipleOf: 0.1), '$data');

        self::assertStringNotContainsString('$data % ', $code);
        self::assertStringContainsString("\$quotient = (float) \$data / 0.1;", $code);
        self::assertStringContainsString('abs($quotient - $rounded)', $code);
        self::assertStringContainsString("'Value must be a multiple of 0.1'", $code);
    }

    #[Test]
    public function generate_emits_throw_only_for_zero_multiple_of(): void
    {
        // multipleOf=0 is mathematically unsatisfiable. The compiler must
        // emit an early `throw new \RuntimeException` and NOT emit `% 0`
        // (which would raise DivisionByZeroError at runtime, bypassing the
        // RuntimeException contract).
        $code = $this->scalar->generate(new Schema(type: 'number', multipleOf: 0.0), '$data');

        self::assertStringContainsString('throw new \RuntimeException', $code);
        self::assertStringContainsString("'multipleOf must be greater than 0'", $code);
        self::assertStringNotContainsString('% 0', $code);
        self::assertStringNotContainsString('/ 0.0', $code);
    }

    #[Test]
    public function generate_combines_all_scalar_constraints_in_canonical_order(): void
    {
        // Order matters: type, enum, const, length, range, pattern,
        // multipleOf. We assert that every emitted block is present and
        // that the type check (which gates value shape) comes first.
        $code = $this->scalar->generate(
            new Schema(
                type: 'string',
                enum: ['a', 'b'],
                const: null,
                hasConst: true,
                minLength: 1,
                maxLength: 10,
                pattern: '^[a-z]+$',
            ),
            '$data',
        );

        $typePos = strpos($code, 'TypeFormatter::format($data)');
        $enumPos = strpos($code, '$__matched = false;');
        $constPos = strpos($code, 'jsonEquals(NULL, $data)');
        $lengthPos = strpos($code, '$utf16Length < 1');
        $patternPos = strpos($code, 'preg_match');

        self::assertNotFalse($typePos);
        self::assertNotFalse($enumPos);
        self::assertNotFalse($constPos);
        self::assertNotFalse($lengthPos);
        self::assertNotFalse($patternPos);

        self::assertLessThan($enumPos, $typePos, 'type check must precede enum check');
        self::assertLessThan($constPos, $enumPos, 'enum check must precede const check');
        self::assertLessThan($lengthPos, $constPos, 'const check must precede length check');
        self::assertLessThan($patternPos, $lengthPos, 'length check must precede pattern check');
    }

    #[Test]
    public function generate_substitutes_data_var_for_nested_property_paths(): void
    {
        $code = $this->scalar->generate(
            new Schema(type: 'string', minLength: 1),
            "\$data['name']",
        );

        self::assertStringContainsString("is_string(\$data['name'])", $code);
        self::assertStringContainsString("\$utf16Bytes = strlen((string) \$data['name']);", $code);
        self::assertStringContainsString("ord(\$data['name'][\$utf16Pos])", $code);
    }

    #[Test]
    public function multiple_of_context_carries_all_five_inputs(): void
    {
        // The MultipleOfContext value-object was introduced to keep
        // ScalarConstraints::generateIntegerMultipleOf at one parameter
        // (§10 rule of three). It carries all five inputs that the
        // original 5-parameter private method received.
        $context = new MultipleOfContext(
            intMultipleOf: 7,
            floatMultipleOfStr: '7',
            epsilonStr: '1.0E-9',
            errorMessage: 'Value must be a multiple of 7',
            valueVar: '$data',
        );

        self::assertSame(7, $context->intMultipleOf);
        self::assertSame('7', $context->floatMultipleOfStr);
        self::assertSame('1.0E-9', $context->epsilonStr);
        self::assertSame('Value must be a multiple of 7', $context->errorMessage);
        self::assertSame('$data', $context->valueVar);
    }

    #[Test]
    public function float_quotient_context_carries_all_four_inputs(): void
    {
        $context = new FloatQuotientContext(
            multipleOfStr: '0.1',
            epsilonStr: '1.0E-9',
            exportedMessage: "'Value must be a multiple of 0.1'",
            valueVar: '$data',
        );

        self::assertSame('0.1', $context->multipleOfStr);
        self::assertSame('1.0E-9', $context->epsilonStr);
        self::assertSame("'Value must be a multiple of 0.1'", $context->exportedMessage);
        self::assertSame('$data', $context->valueVar);
    }

    #[Test]
    public function generate_produces_byte_identical_output_for_integer_multiple_of(): void
    {
        // Pinned byte-for-byte: any change to the emitted codegen for
        // multipleOf=2 (canonical integer case) breaks this test.
        // Uses Schema WITHOUT type to isolate just the multipleOf block.
        // Integer path has ONE trailing newline (the float path adds an
        // extra "\n" in generateMultipleOfCheck — see the float test).
        $code = $this->scalar->generate(new Schema(multipleOf: 2), '$data');

        $expected = <<<'PHP'
        if (is_int($data)) {
            if (0 !== ($data % 2)) {
                throw new \RuntimeException('Value must be a multiple of 2.0');
            }
        } else {
            $quotient = (float) $data / 2.0;
            $rounded = round($quotient);
            $epsilon = 1.0E-9 * max(1.0, abs($quotient));
            if (abs($quotient - $rounded) >= $epsilon) {
                throw new \RuntimeException('Value must be a multiple of 2.0');
            }
        }

PHP;

        self::assertSame($expected, $code);
    }

    #[Test]
    public function generate_produces_byte_identical_output_for_float_multiple_of(): void
    {
        // Uses Schema WITHOUT type to isolate just the float-quotient block.
        $code = $this->scalar->generate(new Schema(multipleOf: 0.1), '$data');

        $expected = <<<'PHP'
            $quotient = (float) $data / 0.1;
            $rounded = round($quotient);
            $epsilon = 1.0E-9 * max(1.0, abs($quotient));
            if (abs($quotient - $rounded) >= $epsilon) {
                throw new \RuntimeException('Value must be a multiple of 0.1');
            }


PHP;

        self::assertSame($expected, $code);
    }
}
