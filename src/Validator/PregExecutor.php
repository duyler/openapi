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

final readonly class PregExecutor
{
    public const int DEFAULT_MAX_BACKTRACKS = 10_000;

    public const int DEFAULT_MAX_RECURSION = 512;

    private const string BACKTRACK_LIMIT_FALLBACK = '1000000';

    private const string RECURSION_LIMIT_FALLBACK = '100000';

    public function __construct(
        private readonly int $maxBacktracks = self::DEFAULT_MAX_BACKTRACKS,
        private readonly int $maxRecursionLimit = self::DEFAULT_MAX_RECURSION,
    ) {}

    /**
     * @param non-empty-string $pattern
     * @param array<array-key, string>|null $matches
     * @param 0|256|512|768 $flags
     * @param-out array<array-key, mixed> $matches
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
     * @param array<array-key, mixed>|null $matches
     * @param int $flags
     * @param-out array<array-key, mixed> $matches
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

    private function assertNoPcreError(): void
    {
        $error = preg_last_error();

        if (PREG_NO_ERROR !== $error) {
            throw new PregRuntimeException(error: $error, message: preg_last_error_msg());
        }
    }
}
