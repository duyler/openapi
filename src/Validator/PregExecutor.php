<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Closure;

use function ini_get;
use function ini_set;
use function preg_match;
use function preg_match_all;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;

/**
 * Defensive wrapper around preg_match / preg_match_all that lowers the
 * process-wide pcre.backtrack_limit before the call and restores the previous
 * value afterwards. Backtracking is the dominant cost factor for catastrophic
 * regular expressions; an attacker controlling the pattern (for example through
 * a JSON-Schema "pattern" field) can otherwise burn hundreds of milliseconds of
 * CPU per request.
 *
 * The wrapper is intentionally dependency-injected: every validator that runs
 * attacker-controlled patterns receives the same immutable instance, configured
 * once by the OpenApiValidatorBuilder. The previous INI value is captured and
 * restored inside a try/finally block so the global state is always returned to
 * its caller-visible value, including when preg_match itself throws or when an
 * inner consumer mutates pcre.backtrack_limit between invocations.
 *
 * PCRE compile errors (malformed pattern) are signalled through a `false`
 * return value mirroring the native contract, and the associated E_WARNING is
 * swallowed via a temporary error handler so callers can branch on the return
 * value without leaking diagnostic noise into PSR-3 logs or PHPUnit output.
 */
final readonly class PregExecutor
{
    public const int DEFAULT_MAX_BACKTRACKS = 10_000;

    public function __construct(
        private readonly int $maxBacktracks = self::DEFAULT_MAX_BACKTRACKS,
    ) {}

    /**
     * @param non-empty-string $pattern
     * @param array<array-key, string>|null $matches populated by reference when present
     * @param 0|256|512|768 $flags bitmask of PREG_OFFSET_CAPTURE and PREG_UNMATCHED_AS_NULL
     *
     * @param-out array<array-key, mixed> $matches
     */
    public function match(string $pattern, string $subject, ?array &$matches = null, int $flags = 0, int $offset = 0): int|false
    {
        $previous = $this->capturePreviousLimit();
        ini_set('pcre.backtrack_limit', (string) $this->maxBacktracks);
        set_error_handler($this->suppressCompileWarning());

        try {
            return preg_match($pattern, $subject, $matches, $flags, $offset);
        } finally {
            restore_error_handler();
            ini_set('pcre.backtrack_limit', $previous);
        }
    }

    /**
     * @param non-empty-string $pattern
     * @param array<array-key, mixed>|null $matches populated by reference when present
     * @param int $flags bitmask of PREG_PATTERN_ORDER, PREG_SET_ORDER, PREG_OFFSET_CAPTURE, PREG_UNMATCHED_AS_NULL
     *
     * @param-out array<array-key, mixed> $matches
     */
    public function matchAll(string $pattern, string $subject, ?array &$matches = null, int $flags = 0, int $offset = 0): int|false
    {
        $previous = $this->capturePreviousLimit();
        ini_set('pcre.backtrack_limit', (string) $this->maxBacktracks);
        set_error_handler($this->suppressCompileWarning());

        try {
            return preg_match_all($pattern, $subject, $matches, $flags, $offset);
        } finally {
            restore_error_handler();
            ini_set('pcre.backtrack_limit', $previous);
        }
    }

    private function capturePreviousLimit(): string
    {
        $previous = ini_get('pcre.backtrack_limit');

        return false === $previous ? '1000000' : $previous;
    }

    /**
     * @return Closure(int, string, string, int): bool
     */
    private function suppressCompileWarning(): Closure
    {
        return static fn(int $errno, string $errstr, string $errfile, int $errline): bool => E_WARNING === $errno;
    }
}
