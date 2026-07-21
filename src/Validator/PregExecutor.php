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
 * process-wide pcre.backtrack_limit and pcre.recursion_limit before the call
 * and restores the previous values afterwards. Backtracking is the dominant
 * cost factor for catastrophic regular expressions (CWE-1333, CWE-400); an
 * attacker controlling the pattern (for example through a JSON-Schema
 * "pattern" field) can otherwise burn hundreds of milliseconds of CPU per
 * request. pcre.recursion_limit bounds the depth of recursion in PCRE's
 * internal matcher: deeply-nested patterns like `(a|a)*b` or `(.*)*$` can
 * exhaust the C stack on systems with small main-thread stacks (Alpine musl
 * libc defaults to 2 MB; Windows PHP builds to 1 MB) and segfault the worker
 * process. DEFAULT_MAX_RECURSION keeps comfortable headroom on typical 8 MB
 * stacks while still bounding pathological inputs.
 *
 * @danger NOT_THREAD_SAFE
 *
 * pcre.backtrack_limit and pcre.recursion_limit are both PHP_INI_ALL
 * (process-global). Under Swoole coroutines or threaded FrankenPHP
 * workers, the capture/restore sequence in {@see match()} and
 * {@see matchAll()} races with concurrent preg_match calls in other
 * coroutines that read or write the same ini variables (O-007,
 * S-020). The ReDoS cap may be silently non-functional for an
 * individual coroutine call: coroutine A lowers the limit, B reads
 * the lowered value as "previous", A restores, B restores to the
 * lowered value -> process stuck with the reduced cap. Prefork
 * runtimes (PHP-FPM, RoadRunner, FrankenPHP non-threaded) are
 * unaffected because each worker owns its own ini scope.
 *
 * The wrapper is intentionally dependency-injected: every validator that runs
 * attacker-controlled patterns receives the same immutable instance, configured
 * once by the OpenApiValidatorBuilder. The previous INI values are captured
 * before each call and restored inside a try/finally block so the global state
 * is always returned to its caller-visible value, including when preg_match
 * itself throws or when an inner consumer mutates the limits between
 * invocations.
 *
 * Process-wide global state caveat (R3-SEC-020): pcre.backtrack_limit and
 * pcre.recursion_limit are both PHP_INI_ALL and therefore process-global.
 * Under Swoole coroutines or threaded FrankenPHP workers, a coroutine that
 * mutates either limit races with any concurrent coroutine that reads or
 * writes the same ini variable. The capture/restore inside try/finally does
 * not close this race — it only narrows the window to the duration of the
 * preg_match call itself. Each coroutine or worker should own its own
 * PregExecutor instance (the default in OpenApiValidatorBuilder) and must not
 * rely on the limits being stable across cooperative yield points.
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
