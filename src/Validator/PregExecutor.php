<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator;

use Duyler\OpenApi\Validator\Exception\PregRuntimeException;

use function ini_get;
use function ini_set;
use function preg_last_error;
use function preg_last_error_msg;
use function restore_error_handler;
use function set_error_handler;

use const E_WARNING;
use const PREG_NO_ERROR;

/**
 * Defensive wrapper around preg_match / preg_match_all that lowers the
 * process-wide pcre.backtrack_limit and pcre.recursion_limit before each
 * call and restores the previous values in a try/finally. Catastrophic
 * backtracking (CWE-1333, CWE-400) on attacker-controlled JSON-Schema
 * "pattern" fields is the dominant CPU cost factor; recursion_limit
 * bounds C-stack exhaustion on deeply-nested patterns. PCRE compile
 * warnings are swallowed and surfaced via the false return value;
 * non-zero preg_last_error() raises PregRuntimeException instead of
 * letting callers misread a runtime failure as "no match".
 *
 * @danger NOT_THREAD_SAFE
 *
 * Both pcre.* ini variables are PHP_INI_ALL (process-global). Under
 * Swoole coroutines / threaded FrankenPHP the capture/restore in
 * {@see match()} and {@see matchAll()} races with concurrent
 * preg_match calls, and the ReDoS cap may be silently non-functional
 * for an individual coroutine call (O-007, S-020). Prefork runtimes
 * are unaffected. See README "Unsafe classes and their contracts".
 */
final readonly class PregExecutor
{
    public const int DEFAULT_MAX_BACKTRACKS = 10_000;

    /**
     * Default cap on PCRE recursion depth. PCRE2 reserves C-stack frames per
     * recursion level; on typical 8 MB main stacks this leaves comfortable
     * headroom while still bounding deeply-nested patterns like `(a|a)*b`.
     */
    public const int DEFAULT_MAX_RECURSION = 512;

    private const string BACKTRACK_LIMIT_FALLBACK = '1000000';

    private const string RECURSION_LIMIT_FALLBACK = '100000';

    public function __construct(
        private readonly int $maxBacktracks = self::DEFAULT_MAX_BACKTRACKS,
        private readonly int $maxRecursionLimit = self::DEFAULT_MAX_RECURSION,
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
        $previousBacktrack = $this->capturePreviousBacktrackLimit();
        $previousRecursion = $this->capturePreviousRecursionLimit();

        ini_set('pcre.backtrack_limit', (string) $this->maxBacktracks);
        ini_set('pcre.recursion_limit', (string) $this->maxRecursionLimit);
        set_error_handler(static fn(int $errno) => E_WARNING === $errno);

        try {
            $result = preg_match($pattern, $subject, $matches, $flags, $offset);
            $this->assertNoPcreError();

            return $result;
        } finally {
            restore_error_handler();
            ini_set('pcre.recursion_limit', $previousRecursion);
            ini_set('pcre.backtrack_limit', $previousBacktrack);
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
        $previousBacktrack = $this->capturePreviousBacktrackLimit();
        $previousRecursion = $this->capturePreviousRecursionLimit();

        ini_set('pcre.backtrack_limit', (string) $this->maxBacktracks);
        ini_set('pcre.recursion_limit', (string) $this->maxRecursionLimit);
        set_error_handler(static fn(int $errno) => E_WARNING === $errno);

        try {
            $result = preg_match_all($pattern, $subject, $matches, $flags, $offset);
            $this->assertNoPcreError();

            return $result;
        } finally {
            restore_error_handler();
            ini_set('pcre.recursion_limit', $previousRecursion);
            ini_set('pcre.backtrack_limit', $previousBacktrack);
        }
    }

    private function capturePreviousBacktrackLimit(): string
    {
        $previous = ini_get('pcre.backtrack_limit');

        return false === $previous ? self::BACKTRACK_LIMIT_FALLBACK : $previous;
    }

    private function capturePreviousRecursionLimit(): string
    {
        $previous = ini_get('pcre.recursion_limit');

        return false === $previous ? self::RECURSION_LIMIT_FALLBACK : $previous;
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
