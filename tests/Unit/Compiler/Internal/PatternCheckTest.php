<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Test\Unit\Compiler\Internal;

use Duyler\OpenApi\Compiler\Internal\PatternCheck;
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

use const E_USER_WARNING;

/**
 * Golden-master + ReDoS-defence tests for the PatternCheck collaborator.
 *
 * The emitted wrapper is the inline defence against catastrophic
 * backtracking (CWE-1333). Every assertion below is also present (via
 * substring matching) in the existing ValidatorCompilerPatternTest —
 * duplication is deliberate: PatternCheckTest is the canonical
 * regression target after the inline wrapper was extracted from
 * ValidatorCompiler into its own collaborator (task 10a).
 */
final class PatternCheckTest extends TestCase
{
    private PatternCheck $patternCheck;

    protected function setUp(): void
    {
        $this->patternCheck = new PatternCheck();
    }

    #[Test]
    public function constant_compiled_max_backtracks_is_10000(): void
    {
        // Sentinel: changing this constant weakens the ReDoS defence.
        // PHP's default `pcre.backtrack_limit = 1_000_000` lets
        // catastrophic-backtracking patterns burn CPU for hundreds of
        // milliseconds; 10_000 is 100x tighter (mirrors
        // PregExecutor::DEFAULT_MAX_BACKTRACKS).
        self::assertSame(10_000, PatternCheck::COMPILED_MAX_BACKTRACKS);
    }

    #[Test]
    public function generate_emits_backtrack_limit_wrapper_substrings(): void
    {
        $code = $this->patternCheck->generate('^[a-z]+$');

        // Each substring corresponds to a mandatory step in the defensive
        // wrapper: capture previous limit, set bounded limit, install
        // error handler, try block, finally block, restore handler, restore
        // limit. Missing any of these breaks the ReDoS contract.
        self::assertStringContainsString("\$previous = ini_get('pcre.backtrack_limit');", $code);
        self::assertStringContainsString("ini_set('pcre.backtrack_limit', '10000');", $code);
        self::assertStringContainsString("set_error_handler(static fn(int \$errno) => E_WARNING === \$errno);", $code);
        self::assertStringContainsString('try {', $code);
        self::assertStringContainsString('} finally {', $code);
        self::assertStringContainsString('restore_error_handler();', $code);
        self::assertStringContainsString("ini_set('pcre.backtrack_limit', \$previous);", $code);
    }

    #[Test]
    public function generate_disambiguates_pcre_error_from_no_match(): void
    {
        // The wrapper must surface `preg_match === false` (compile error)
        // with a distinct message from `preg_match === 0` (no match) so
        // logs do not conflate the two.
        $code = $this->patternCheck->generate('^[a-z]+$');

        self::assertStringContainsString("'PCRE error during pattern validation'", $code);
        self::assertStringContainsString("'Pattern validation failed'", $code);
        self::assertStringContainsString('false === $result', $code);
        self::assertStringContainsString('0 === $result', $code);
    }

    #[Test]
    public function generate_normalises_pattern_via_regex_validator(): void
    {
        // Bare patterns without delimiters must be normalised before being
        // embedded in the emitted preg_match call. This is delegated to
        // RegexValidator (the same normaliser used by the runtime path).
        $code = $this->patternCheck->generate('[a-z]+');

        // RegexValidator::normalize wraps bare patterns in '#' delimiters.
        self::assertStringContainsString("'#[a-z]+#'", $code);
    }

    #[Test]
    public function generate_preserves_pattern_with_explicit_delimiters(): void
    {
        $code = $this->patternCheck->generate('/^[a-z]+$/');

        self::assertStringContainsString("'/^[a-z]+\$/'", $code);
    }

    #[Test]
    public function generate_substitutes_value_var_for_nested_paths(): void
    {
        $code = $this->patternCheck->generate('^a$', "\$data['name']");

        self::assertStringContainsString('preg_match', $code);
        self::assertStringContainsString("(string) \$data['name']", $code);
    }

    #[Test]
    public function generated_wrapper_bounded_under_catastrophic_backtracking(): void
    {
        // R3-SEC-001 anti-DoS: `(a+)+$` is the canonical
        // catastrophic-backtracking pattern. PHP's default
        // `pcre.backtrack_limit = 1_000_000` lets the engine burn hundreds
        // of milliseconds on 30-byte attacker input; the inline wrapper
        // caps it at 10 000 which aborts near-instantly. The 500 ms upper
        // bound gives CI stability margin (local baseline <50 ms).
        $code = $this->patternCheck->generate('^(a+)+$');
        $validator = $this->evaluateGeneratedWrapper($code, 'CatastrophicBacktrackingCollaboratorValidator');

        $data = str_repeat('a', 30) . '!';

        $start = microtime(true);

        try {
            $validator->validate($data);
        } catch (RuntimeException) {
            // Expected: bounded execution surfaces as either a "PCRE error"
            // or "Pattern validation failed" RuntimeException.
        }

        $elapsed = (microtime(true) - $start) * 1_000_000.0;

        self::assertLessThan(
            500_000.0,
            $elapsed,
            sprintf('Catastrophic-backtracking pattern took %.0f μs; wrapper failed to bound execution.', $elapsed),
        );
    }

    #[Test]
    public function generated_wrapper_restores_previous_backtrack_limit(): void
    {
        // Defence-in-depth invariant: the wrapper captures
        // `pcre.backtrack_limit` before lowering it and restores the
        // captured value inside `finally`. Without this, the global limit
        // would leak at 10 000 for the rest of the process (Swoole /
        // RoadRunner / FrankenPHP workers).
        $priorLimit = ini_get('pcre.backtrack_limit');

        try {
            ini_set('pcre.backtrack_limit', '999');

            $code = $this->patternCheck->generate('^[a-z]+$');
            $validator = $this->evaluateGeneratedWrapper($code, 'RestoreLimitCollaboratorValidator');

            $validator->validate('abc');

            self::assertSame('999', ini_get('pcre.backtrack_limit'));
        } finally {
            ini_set('pcre.backtrack_limit', false === $priorLimit ? '1000000' : $priorLimit);
        }
    }

    #[Test]
    public function generated_wrapper_restores_handler_on_exception(): void
    {
        // The wrapper installs a bounded-scope error handler that
        // suppresses E_WARNING from `preg_match` (raised on PCRE compile
        // errors). The handler must be restored via
        // `restore_error_handler()` in the `finally` block even when the
        // validator throws.
        $code = $this->patternCheck->generate('^[a-z]+$');
        $validator = $this->evaluateGeneratedWrapper($code, 'RestoreHandlerCollaboratorValidator');

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

            // Probe the handler stack with a sentinel E_USER_WARNING.
            trigger_error('probe', E_USER_WARNING);

            self::assertTrue(
                $handlerCalled,
                'Sentinel error handler was not invoked after the validator threw — restore_error_handler did not run in finally.',
            );
        } finally {
            restore_error_handler();
        }
    }

    #[Test]
    public function generate_produces_byte_identical_output_for_canonical_pattern(): void
    {
        $code = $this->patternCheck->generate('^[a-z]+$');

        // Pinned byte-for-byte from the pre-refactor
        // ValidatorCompiler::generatePatternCheck output. The pattern
        // `^[a-z]+$` is normalised by RegexValidator to `#^[a-z]+$#`
        // (canonical '#' delimiters).
        $expected = <<<'PHP'
        $previous = ini_get('pcre.backtrack_limit');
        $previous = false === $previous ? '1000000' : $previous;
        ini_set('pcre.backtrack_limit', '10000');
        set_error_handler(static fn(int $errno) => E_WARNING === $errno);
        try {
            $result = preg_match('#^[a-z]+$#', (string) $data);
            if (false === $result) {
                throw new \RuntimeException('PCRE error during pattern validation');
            }
            if (0 === $result) {
                throw new \RuntimeException('Pattern validation failed');
            }
        } finally {
            restore_error_handler();
            ini_set('pcre.backtrack_limit', $previous);
        }


PHP;

        self::assertSame($expected, $code);
    }

    private function evaluateGeneratedWrapper(string $generatedBody, string $shortName): object
    {
        // Wrap the emitted body in a minimal validate() so we can exercise
        // the runtime semantics of the inlined wrapper.
        $code = "<?php\n\n";
        $code .= "final class $shortName {\n";
        $code .= "    public function validate(mixed \$data): void\n";
        $code .= "    {\n";
        $code .= $generatedBody;
        $code .= "    }\n";
        $code .= "}\n";

        $evalCode = str_replace('declare(strict_types=1);', '', substr($code, 5));

        /**
         * The collaborator is invoked under test control with trusted
         * patterns. The eval here is the documented contract for
         * exercising compiled codegen (see ValidatorCompilerTest).
         */
        eval($evalCode);

        return new $shortName();
    }
}
