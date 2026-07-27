<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function sprintf;

/**
 * Emits array-constraint codegen (minItems, maxItems, uniqueItems) for
 * the ValidatorCompiler. The uniqueItems block emits a call to the
 * inlined `canonicalJsonKey` helper, which the compiler appends to the
 * generated class footer via EqualityHelpers when the schema tree
 * requires it (enum / const / uniqueItems). Items iteration (the
 * `foreach` + recursive constraint emission) is owned by the
 * orchestrator because it recurses through the full schema tree.
 */
final readonly class ArrayConstraints
{
    public function __construct(
        private EqualityHelpers $equalityHelpers = new EqualityHelpers(),
    ) {}

    public function generateLengthConstraints(Schema $schema, string $dataVar): string
    {
        $code = '';

        if (null !== $schema->minItems) {
            $code .= sprintf("        if (count(%s) < %d) {\n", $dataVar, $schema->minItems);
            $code .= "            throw new \\RuntimeException('Array too short');\n";
            $code .= "        }\n\n";
        }

        if (null !== $schema->maxItems) {
            $code .= sprintf("        if (count(%s) > %d) {\n", $dataVar, $schema->maxItems);
            $code .= "            throw new \\RuntimeException('Array too long');\n";
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
        $code .= "            if (100000 < count(\$__seen)) {\n";
        $code .= "                throw new \\RuntimeException('Too many items for unique check');\n";
        $code .= "            }\n";
        $code .= "        }\n\n";

        return $code;
    }
}
