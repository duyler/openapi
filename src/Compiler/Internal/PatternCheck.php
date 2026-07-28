<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Validator\Schema\RegexValidator;

use function sprintf;
use function var_export;

/**
 * Emits the inlined PCRE pattern-match wrapper that defends against
 * catastrophic backtracking (CWE-1333, ReDoS). The wrapper lowers
 * `pcre.backtrack_limit` for the duration of the `preg_match` call and
 * restores the previous value inside `try`/`finally`, so the standalone
 * generated validator cannot be turned into a CPU-exhaustion vector when
 * matched against attacker-controlled input.
 *
 * Security invariants enforced byte-for-byte in the emitted code:
 * - `pcre.backtrack_limit` is captured BEFORE it is lowered;
 * - `set_error_handler` is installed to suppress PCRE compile warnings;
 * - `restore_error_handler` + `ini_set` run inside `finally` so they always
 *   execute, even when the validator throws;
 * - `preg_match === false` (PCRE compile error) is disambiguated from
 *   `preg_match === 0` (no match) via distinct `RuntimeException` messages.
 *
 * @internal
 */
final readonly class PatternCheck
{
    public const int COMPILED_MAX_BACKTRACKS = 10_000;

    public function generate(string $pattern, string $valueVar = '$data'): string
    {
        $normalizedPattern = new RegexValidator()->normalize($pattern);
        $escapedPattern = var_export($normalizedPattern, true);
        $limit = var_export((string) self::COMPILED_MAX_BACKTRACKS, true);

        $code = "        \$previous = ini_get('pcre.backtrack_limit');\n";
        $code .= "        \$previous = false === \$previous ? '1000000' : \$previous;\n";
        $code .= sprintf("        ini_set('pcre.backtrack_limit', %s);\n", $limit);
        $code .= "        set_error_handler(static fn(int \$errno) => E_WARNING === \$errno);\n";
        $code .= "        try {\n";
        $code .= sprintf("            \$result = preg_match(%s, (string) %s);\n", $escapedPattern, $valueVar);
        $code .= "            if (false === \$result) {\n";
        $code .= "                throw new \\RuntimeException('PCRE error during pattern validation');\n";
        $code .= "            }\n";
        $code .= "            if (0 === \$result) {\n";
        $code .= "                throw new \\RuntimeException('Pattern validation failed');\n";
        $code .= "            }\n";
        $code .= "        } finally {\n";
        $code .= "            restore_error_handler();\n";
        $code .= "            ini_set('pcre.backtrack_limit', \$previous);\n";
        $code .= "        }\n\n";

        return $code;
    }
}
