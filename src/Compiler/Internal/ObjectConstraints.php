<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function array_keys;
use function sprintf;
use function var_export;

/**
 * Emits object-keyword codegen (required, additionalProperties:false) for
 * the ValidatorCompiler. Property recursion and items iteration stay in
 * the orchestrator because they traverse the schema tree via the shared
 * recursive `generateConstraintsForSchema` entry point.
 */
final readonly class ObjectConstraints
{
    /**
     * @param list<string> $required
     */
    public function generateRequiredCheck(array $required, string $dataVar): string
    {
        $code = '';

        foreach ($required as $propertyName) {
            $safeName = var_export($propertyName, true);
            $code .= sprintf("        if (false === array_key_exists(%s, %s)) {\n", $safeName, $dataVar);
            $code .= sprintf(
                "            throw new \\RuntimeException(sprintf('Required property missing: %%s', %s));\n",
                $safeName,
            );
            $code .= "        }\n";
        }

        return $code . "\n";
    }

    public function generateAdditionalPropertiesCheck(Schema $schema, string $dataVar = '$data'): string
    {
        if (null === $schema->properties) {
            return '';
        }

        $exportedKeys = var_export(array_keys($schema->properties), true);

        $code = sprintf("        foreach (array_keys(%s) as \$key) {\n", $dataVar);
        $code .= sprintf("            if (false === in_array(\$key, %s, true)) {\n", $exportedKeys);
        $code .= "                throw new \\RuntimeException(sprintf('Additional property not allowed: %s', var_export(\$key, true)));\n";
        $code .= "            }\n";
        $code .= "        }\n\n";

        return $code;
    }
}
