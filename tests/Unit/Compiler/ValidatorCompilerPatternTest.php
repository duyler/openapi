<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler;

use Duyler\OpenApi\Compiler\ValidatorCompiler;
use Duyler\OpenApi\Schema\Model\Schema;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function ini_get;
use function ini_set;
use function microtime;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_repeat;
use function str_replace;
use function substr;
use function trigger_error;
use function usleep;

use const E_USER_WARNING;

final class ValidatorCompilerPatternTest extends TestCase
{
    /**
     * R3-SEC-001 presence check: the generated validator must inline the
     * PregExecutor-style defensive wrapper. Each substring corresponds to a
     * mandatory step: capture previous limit, set bounded limit, install
     * error handler, restore handler, restore limit, try/finally骨架.
     */
    #[Test]
    public function compiled_pattern_check_includes_backtrack_limit_wrapper(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $code = $compiler->compile($schema, 'PatternWrapperPresenceValidator');

        self::assertStringContainsString("ini_set('pcre.backtrack_limit'", $code);
        self::assertStringContainsString('set_error_handler', $code);
        self::assertStringContainsString('restore_error_handler', $code);
        self::assertStringContainsString('finally', $code);
        self::assertStringContainsString('$previous', $code);
    }

    /**
     * R3-SEC-001 anti-DoS: catastrophic-backtracking pattern `(a+)+$`
     * against attacker-controlled 31-byte input must execute in bounded
     * time. PHP's default `pcre.backtrack_limit = 1_000_000` lets the
     * engine burn hundreds of milliseconds; the inline wrapper caps it at
     * 10 000 which aborts near-instantly. The 500 ms upper bound gives CI
     * stability margin (local baseline <50 ms).
     */
    #[Test]
    public function compiled_pattern_check_bounded_under_catastrophic_backtracking(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^(a+)+$');

        $validator = $this->evaluateCompiledClass($compiler, $schema, 'CatastrophicBacktrackingValidator');

        $data = str_repeat('a', 30) . '!';

        $start = microtime(true);

        try {
            $validator->validate($data);
        } catch (RuntimeException) {
            // Expected: bounded execution surfaces as either a "PCRE error"
            // or "Pattern validation failed" RuntimeException. The defence
            // is the time bound, not the specific message.
        }

        $elapsed = (microtime(true) - $start) * 1_000_000.0;

        self::assertLessThan(
            500_000.0,
            $elapsed,
            sprintf('Catastrophic-backtracking pattern took %.0f μs; wrapper failed to bound execution.', $elapsed),
        );
    }

    /**
     * Positive path: matching input must not throw. Verifies the wrapper
     * does not over-reject valid matches.
     */
    #[Test]
    public function compiled_pattern_check_passes_for_matching_input(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $validator = $this->evaluateCompiledClass($compiler, $schema, 'PatternMatchingInputValidator');

        $validator->validate('abc');

        self::assertTrue(true, 'Matching input passes pattern validation without throwing.');
    }

    /**
     * R3-PERF-001 disambiguation: a non-matching input must throw
     * RuntimeException with message 'Pattern validation failed' (not the
     * PCRE-error message). This distinguishes "data does not match
     * pattern" from "PCRE ran out of resources".
     */
    #[Test]
    public function compiled_pattern_check_throws_pattern_validation_failed_for_non_matching(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $validator = $this->evaluateCompiledClass($compiler, $schema, 'PatternNonMatchingInputValidator');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Pattern validation failed');

        $validator->validate('abc123');
    }

    /**
     * R3-PERF-001 disambiguation: a malformed pattern that passes
     * RegexValidator::normalize (which only adds delimiters — it does not
     * compile-check) but fails PCRE compilation at runtime must surface as
     * a RuntimeException with the dedicated message 'PCRE error during
     * pattern validation'. Before the fix, `false === preg_match(...)`
     * conflated this with the no-match case and reported
     * 'Pattern validation failed', misleading operators in logs.
     */
    #[Test]
    public function compiled_pattern_check_disambiguates_pcre_error(): void
    {
        $compiler = new ValidatorCompiler();
        // '[' has no delimiters and an unclosed character class.
        // normalize() wraps it as '#[#' without compile-checking; the
        // compile-error surfaces at validate() time as preg_match === false.
        $schema = new Schema(type: 'string', pattern: '[');

        $validator = $this->evaluateCompiledClass($compiler, $schema, 'MalformedPatternPcreErrorValidator');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PCRE error during pattern validation');

        $validator->validate('any-input');
    }

    /**
     * Defence-in-depth invariants: the inline wrapper captures
     * `pcre.backtrack_limit` before lowering it and restores the captured
     * value inside `finally`. Without this, the global limit would leak at
     * 10 000 for the rest of the process (Swoole / RoadRunner / FrankenPHP
     * workers — see README "Long-Running Processes"). The test sets a
     * sentinel value '999', runs the validator on a passing input, then
     * asserts the sentinel survives.
     */
    #[Test]
    public function compiled_pattern_check_restores_previous_backtrack_limit(): void
    {
        $priorLimit = ini_get('pcre.backtrack_limit');

        try {
            ini_set('pcre.backtrack_limit', '999');

            $compiler = new ValidatorCompiler();
            $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

            $validator = $this->evaluateCompiledClass($compiler, $schema, 'RestoreLimitValidator');

            $validator->validate('abc');

            self::assertSame('999', ini_get('pcre.backtrack_limit'));
        } finally {
            ini_set('pcre.backtrack_limit', false === $priorLimit ? '1000000' : $priorLimit);
        }
    }

    /**
     * The inline wrapper installs a bounded-scope error handler that
     * suppresses E_WARNING from `preg_match` (raised on PCRE compile
     * errors). The handler must be restored via `restore_error_handler()`
     * in the `finally` block even when the validator throws. The test
     * installs a sentinel handler, runs the validator on non-matching
     * input (which throws inside the wrapper), catches the exception, and
     * then triggers an E_USER_WARNING: if `restore_error_handler` ran, the
     * sentinel handler runs and increments the counter.
     */
    #[Test]
    public function compiled_pattern_check_restores_handler_on_exception(): void
    {
        $compiler = new ValidatorCompiler();
        $schema = new Schema(type: 'string', pattern: '^[a-z]+$');

        $validator = $this->evaluateCompiledClass($compiler, $schema, 'RestoreHandlerOnExceptionValidator');

        $handlerCalled = false;
        set_error_handler(static function (int $errno) use (&$handlerCalled): bool {
            if (E_USER_WARNING === $errno) {
                $handlerCalled = true;

                return true;
            }

            return false;
        });

        try {
            try {
                $validator->validate('abc123');
                self::fail('Expected RuntimeException for non-matching input.');
            } catch (RuntimeException) {
                // Expected.
            }

            // Give the engine a beat; then probe the handler stack.
            usleep(0);
            trigger_error('probe', E_USER_WARNING);

            self::assertTrue(
                $handlerCalled,
                'Sentinel error handler was not invoked after the validator threw — restore_error_handler did not run in finally.',
            );
        } finally {
            restore_error_handler();
        }
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
}
