<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use function sprintf;

/**
 * Emits the inlined UTF-16 code-unit length computation used by string
 * minLength/maxLength validation. The compiled loop walks UTF-8 bytes once
 * and converts each code point to its UTF-16 length (1 or 2 code units for
 * code points outside the BMP).
 */
final readonly class Utf16Length
{
    /**
     * Emits a self-contained byte-walking loop that leaves the UTF-16 code-unit
     * length in a local `$utf16Length` variable.
     */
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
