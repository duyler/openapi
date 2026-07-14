<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Format\BuiltinFormats;
use Duyler\OpenApi\Validator\SchemaValidator\SchemaValidator;
use Duyler\OpenApi\Validator\ValidatorPool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function sprintf;

final class ValidatorCompilerIntegerMultipleOfTest extends TestCase
{
    /**
     * AC #1: type:integer must accept the whole float 3.0 in both the
     * runtime validator and the compiled validator (JSON Schema 2020-12
     * §4.2.3 — a number with zero fractional part is an integer).
     *
     * Anti-test (Acceptance Criteria line 140): reverting the compiler to
     * the old `is_int($data)` check would make this assertion fail, because
     * `is_int(3.0)` is false. The compiled generator must therefore emit
     * the whole-float acceptance expression `is_int || (is_float && fmod
     * === 0.0 && !inf && !nan)`.
     */
    #[Test]
    public function compiled_integer_accepts_whole_float(): void
    {
        $schema = new Schema(type: 'integer');

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'IntegerWholeFloatValidator');

        self::assertStringContainsString('is_int($data)', $code);
        self::assertStringContainsString('is_float($data)', $code);
        self::assertStringContainsString('0.0 === fmod($data, 1.0)', $code);
        self::assertStringContainsString('!is_infinite($data)', $code);
        self::assertStringContainsString('!is_nan($data)', $code);

        $this->assertParity($schema, 3.0, true, 'IntegerWholeFloat');
    }

    /**
     * AC #2: type:integer must reject the non-whole float 3.14 in both
     * validators — fractional part is non-zero.
     */
    #[Test]
    public function compiled_integer_rejects_non_whole_float(): void
    {
        $schema = new Schema(type: 'integer');

        $this->assertParity($schema, 3.14, false, 'IntegerNonWholeFloat');
    }

    /**
     * AC #3: a large dividend (1e20) divided by a small multipleOf (0.1)
     * exercises the float-path. Runtime and compiled must agree — both
     * accept or both reject, regardless of which one it is. The previous
     * compiled implementation used `fmod` directly on the dividend, which
     * lost precision near IEEE-754 limits and silently diverged.
     */
    #[Test]
    public function compiled_multiple_of_large_float_parity_with_runtime(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.1);

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'MultipleOfLargeFloatGenerator');
        self::assertStringNotContainsString('fmod((float) $data', $code);

        $runtime = $this->runtimeAccepts($schema, 1e20);
        $compiled = $this->compiledAccepts($schema, 1e20, 'MultipleOfLargeFloatValidator');

        self::assertSame(
            $runtime,
            $compiled,
            sprintf('Parity failure for 1e20 multipleOf=0.1: runtime=%s, compiled=%s.', $runtime ? 'accept' : 'reject', $compiled ? 'accept' : 'reject'),
        );
    }

    /**
     * AC #4: multipleOf=2 must reject 7 — exercises the int-path with the
     * exact `%` modulus operator (no float rounding).
     */
    #[Test]
    public function compiled_multiple_of_integer_rejects_non_multiple(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 2);

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'MultipleOfIntegerRejectValidator');
        self::assertStringContainsString('if (is_int($data))', $code);
        self::assertStringContainsString('0 !== ($data % 2)', $code);

        $this->assertParity($schema, 7, false, 'MultipleOfIntegerReject');
    }

    /**
     * AC positive: multipleOf=2 must accept 8 — exercises the int-path
     * accept case.
     */
    #[Test]
    public function compiled_multiple_of_integer_accepts_exact_multiple(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 2);

        $this->assertParity($schema, 8, true, 'MultipleOfIntegerAccept');
    }

    /**
     * AC #5: multipleOf=0.01 must accept 0.03 — exercises the float-path
     * with a small-magnitude multipleOf where fmod previously gave 0.0099
     * and rejected the value.
     */
    #[Test]
    public function compiled_multiple_of_small_float_accepts(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.01);

        $this->assertParity($schema, 0.03, true, 'MultipleOfSmallFloat');
    }

    /**
     * Regression for multipleOf=0.0. The int-path guard would otherwise
     * emit `$data % 0` which raises DivisionByZeroError in PHP 8.0+ —
     * an unrecoverable error that bypasses the runtime validator's
     * contract of throwing MultipleOfKeywordError. Runtime always rejects
     * any data when multipleOf is zero, so the compiled validator must
     * also reject any data with a regular RuntimeException.
     */
    #[Test]
    public function compiled_multiple_of_zero_throws_runtime_exception_not_division_by_zero(): void
    {
        $schema = new Schema(type: 'number', multipleOf: 0.0);

        $compiler = new ValidatorCompiler();
        $code = $compiler->compile($schema, 'MultipleOfZeroCheck');

        self::assertStringContainsString('throw new \\RuntimeException', $code);
        self::assertStringNotContainsString('% 0', $code);
        self::assertStringNotContainsString('/ 0.0', $code);

        $this->assertParity($schema, 5, false, 'MultipleOfZero');
    }

    private function evaluateCompiledClass(ValidatorCompiler $compiler, Schema $schema, string $shortName): object
    {
        $code = $compiler->compile($schema, $shortName);

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));

        /**
         * Generated code is produced by ValidatorCompiler for trusted schemas
         * (OpenAPI documents under our control) and is parsed during tests via
         * token_get_all elsewhere. This eval is the documented contract for
         * exercising compiled validators (see ValidatorCompilerTest).
         */
        eval($evalCode);

        return new $shortName();
    }

    private function runtimeAccepts(Schema $schema, mixed $data): bool
    {
        $pool = new ValidatorPool();
        $validator = new SchemaValidator($pool, BuiltinFormats::create());

        try {
            $validator->validate($data, $schema);
        } catch (Throwable) {
            return false;
        }

        return true;
    }

    private function compiledAccepts(Schema $schema, mixed $data, string $shortName): bool
    {
        $compiler = new ValidatorCompiler();
        $validator = $this->evaluateCompiledClass($compiler, $schema, $shortName);

        try {
            $validator->validate($data);
        } catch (RuntimeException) {
            return false;
        }

        return true;
    }

    private function assertParity(Schema $schema, mixed $data, bool $expected, string $label): void
    {
        $runtime = $this->runtimeAccepts($schema, $data);
        $compiled = $this->compiledAccepts($schema, $data, 'Parity' . $label . 'Validator');

        self::assertSame(
            $expected,
            $runtime,
            sprintf('Runtime validator must %s for "%s".', $expected ? 'accept' : 'reject', $label),
        );
        self::assertSame(
            $expected,
            $compiled,
            sprintf('Compiled validator must %s for "%s".', $expected ? 'accept' : 'reject', $label),
        );
        self::assertSame(
            $runtime,
            $compiled,
            sprintf('Parity failure for "%s": runtime=%s, compiled=%s.', $label, $runtime ? 'accept' : 'reject', $compiled ? 'accept' : 'reject'),
        );
    }
}
