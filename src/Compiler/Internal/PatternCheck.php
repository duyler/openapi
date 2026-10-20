<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Validator\Schema\RegexValidator;

use function sprintf;
use function var_export;

final readonly class PatternCheck
{
    public const int COMPILED_MAX_BACKTRACKS = 10_000;

    public const string PCRE_DEFAULT_BACKTRACK_LIMIT = '1000000';

    public function generate(string $pattern, string $valueVar = '$data'): string
    {
        $normalizedPattern = new RegexValidator()->normalize($pattern);
        $escapedPattern = var_export($normalizedPattern, true);
        $limit = var_export((string) self::COMPILED_MAX_BACKTRACKS, true);
        $defaultLimit = var_export(self::PCRE_DEFAULT_BACKTRACK_LIMIT, true);

        $code = "        \$previous = ini_get('pcre.backtrack_limit');\n";
        $code .= sprintf("        \$previous = false === \$previous ? %s : \$previous;\n", $defaultLimit);
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
