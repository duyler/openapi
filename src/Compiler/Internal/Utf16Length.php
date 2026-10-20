<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use function sprintf;

final readonly class Utf16Length
{
    public function generate(string $valueVar = '$data'): string
    {
        $code = "        \$utf16Length = 0;\n";
        $code .= sprintf("        \$utf16Bytes = strlen((string) %s);\n", $valueVar);
        $code .= "        \$utf16Pos = 0;\n";
        $code .= "        while (\$utf16Pos < \$utf16Bytes) {\n";
        $code .= sprintf("            \$utf16Octet = ord(%s[\$utf16Pos]);\n", $valueVar);
        $code .= "            if (\$utf16Octet < 0x80) {\n";
        $code .= "                \$utf16Pos += 1;\n";
        $code .= "                \$utf16Length += 1;\n";
        $code .= "            } elseif (\$utf16Octet < 0xC0) {\n";
        $code .= "                \$utf16Pos += 1;\n";
        $code .= "            } elseif (\$utf16Octet < 0xE0) {\n";
        $code .= "                \$utf16Pos += 2;\n";
        $code .= "                \$utf16Length += 1;\n";
        $code .= "            } elseif (\$utf16Octet < 0xF0) {\n";
        $code .= "                \$utf16Pos += 3;\n";
        $code .= "                \$utf16Length += 1;\n";
        $code .= "            } else {\n";
        $code .= "                \$utf16Pos += 4;\n";
        $code .= "                \$utf16Length += 2;\n";
        $code .= "            }\n";
        $code .= "        }\n";

        return $code;
    }
}
