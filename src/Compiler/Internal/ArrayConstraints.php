<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function sprintf;
use function var_export;

final readonly class ArrayConstraints
{
    public const int MAX_UNIQUE_CHECK_ITEMS = 100_000;

    public function __construct(
        private EqualityHelpers $equalityHelpers = new EqualityHelpers(),
    ) {}

    public function generateLengthConstraints(
        Schema $schema,
        string $dataVar,
        string $path = GenerationContext::ROOT_PATH,
    ): string {
        $code = '';

        if (null !== $schema->minItems) {
            $code .= sprintf("        if (count(%s) < %d) {\n", $dataVar, $schema->minItems);
            $code .= $this->throwRuntime(sprintf('Array too short at %s', $path));
            $code .= "        }\n\n";
        }

        if (null !== $schema->maxItems) {
            $code .= sprintf("        if (count(%s) > %d) {\n", $dataVar, $schema->maxItems);
            $code .= $this->throwRuntime(sprintf('Array too long at %s', $path));
            $code .= "        }\n\n";
        }

        if ($schema->uniqueItems) {
            $code .= $this->generateUniqueItemsCheck($dataVar);
        }

        return $code;
    }

    public function generateUniqueItemsCheck(string $valueVar = '$data'): string
    {
        $code = "        \$__seen = [];\n";
        $code .= sprintf("        foreach (%s as \$__item) {\n", $valueVar);
        $code .= "            try {\n";
        $code .= "                \$__key = \$this->canonicalJsonKey(\$__item);\n";
        $code .= "            } catch (\\JsonException \$__e) {\n";
        $code .= "                throw new \\RuntimeException(sprintf('Failed to encode value for uniqueness check: %s', \$__e->getMessage()), 0, \$__e);\n";
        $code .= "            }\n";
        $code .= "            if (isset(\$__seen[\$__key])) {\n";
        $code .= "                throw new \\RuntimeException('Array items must be unique');\n";
        $code .= "            }\n";
        $code .= "            \$__seen[\$__key] = true;\n";
        $code .= sprintf("            if (%d < count(\$__seen)) {\n", self::MAX_UNIQUE_CHECK_ITEMS);
        $code .= "                throw new \\RuntimeException('Too many items for unique check');\n";
        $code .= "            }\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function throwRuntime(string $message): string
    {
        return sprintf("            throw new \\RuntimeException(%s);\n", var_export($message, true));
    }
}
