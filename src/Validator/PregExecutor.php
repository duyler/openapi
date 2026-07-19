<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Validator\Exception\PregRuntimeException;

use function ini_get;
use function ini_set;
use function preg_last_error;
use function preg_last_error_msg;
use function preg_match;
use function preg_match_all;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;
use const PREG_NO_ERROR;

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
 *
 * A non-zero preg_last_error() after the call indicates an internal PCRE
 * failure (backtrack_limit exceeded, recursion_limit exceeded, JIT stack
 * overflow). The return value cannot be trusted in that state, so the wrapper
 * raises a PregRuntimeException rather than silently returning `false`, which
 * callers would otherwise misread as "no match".
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
     *
     * @throws PregRuntimeException when preg_last_error() returns a non-zero code
     */
    public function match(string $pattern, string $subject, ?array &$matches = null, int $flags = 0, int $offset = 0): int|false
    {
        $previous = $this->capturePreviousLimit();
        ini_set('pcre.backtrack_limit', (string) $this->maxBacktracks);
        set_error_handler(static fn(int $errno) => E_WARNING === $errno);

        try {
            $result = preg_match($pattern, $subject, $matches, $flags, $offset);
            $this->assertNoPcreError();

            return $result;
        } finally {
            restore_error_handler();
            ini_set('pcre.backtrack_limit', $previous);
        }
    }

    /**
     * @param non-empty-string $pattern
     * @param array<array-key, mixed>|null $matches populated by reference when present
     * @param int $flags bitmask of PREG_PATTERNORDER, PREG_SET_ORDER, PREG_OFFSET_CAPTURE, PREG_UNMATCHED_AS_NULL
     *
     * @param-out array<array-key, mixed> $matches
     *
     * @throws PregRuntimeException when preg_last_error() returns a non-zero code
     */
    public function matchAll(string $pattern, string $subject, ?array &$matches = null, int $flags = 0, int $offset = 0): int|false
    {
        $previous = $this->capturePreviousLimit();
        ini_set('pcre.backtrack_limit', (string) $this->maxBacktracks);
        set_error_handler(static fn(int $errno) => E_WARNING === $errno);

        try {
            $result = preg_match_all($pattern, $subject, $matches, $flags, $offset);
            $this->assertNoPcreError();

            return $result;
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
     * @throws PregRuntimeException when preg_last_error() returns a non-zero code
     */
    private function assertNoPcreError(): void
    {
        $error = preg_last_error();

        if (PREG_NO_ERROR !== $error) {
            throw new PregRuntimeException(error: $error, message: preg_last_error_msg());
        }
    }
}
