<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use InvalidArgumentException;
use RuntimeException;

use function addslashes;
use function array_filter;
use function array_keys;
use function array_map;
use function array_pop;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function preg_match;
use function sprintf;
use function var_export;

/**
 * @experimental
 */
final readonly class ValidatorCompiler
{
    private const int REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH = 21;

    private const string CLASS_NAME_PATTERN = '/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/';

    /**
     * Relative epsilon factor used for the float-path of the multipleOf
     * check. Mirrors NumericRangeValidator::RELATIVE_EPSILON_FACTOR so the
     * compiled validator stays numerically equivalent to the runtime one.
     */
    private const float RELATIVE_EPSILON_FACTOR = 1e-9;

    public function compile(Schema $schema, string $className): string
    {
        $this->validateClassName($className);

        $unsupported = $this->detectUnsupportedKeywords($schema);

        if ([] !== $unsupported) {
            throw new UnsupportedKeywordException($unsupported);
        }

        return $this->generateCode($schema, $className);
    }

    public function compileWithRefResolution(
        Schema $schema,
        string $className,
        OpenApiDocument $document,
    ): string {
        [$resolvedSchema,] = $this->resolveRefs($schema, $document);
        return $this->compile($resolvedSchema, $className);
    }

    public function compileWithCache(
        Schema $schema,
        string $className,
        ?CompilationCacheInterface $cache = null,
        ?OpenApiDocument $document = null,
    ): string {
        if (null === $cache) {
            return $this->compile($schema, $className);
        }

        $schemaHash = $cache->generateKey($schema, $className, $document);
        $cached = $cache->get($schemaHash);

        if (null !== $cached) {
            return $cached;
        }

        $code = $this->compile($schema, $className);
        $cache->set($schemaHash, $code);

        return $code;
    }

    private function validateClassName(string $className): void
    {
        $parts = explode('\\', $className);

        foreach ($parts as $part) {
            if (1 !== preg_match(self::CLASS_NAME_PATTERN, $part)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid class name part: "%s"', $part),
                );
            }
        }
    }

    private function generateCode(Schema $schema, string $className): string
    {
        $parts = explode('\\', $className);
        $shortName = array_pop($parts);
        $namespace = implode('\\', $parts);

        $code = "<?php\n\n";
        $code .= "declare(strict_types=1);\n\n";

        if ('' !== $namespace) {
            $code .= sprintf("namespace %s;\n\n", $namespace);
        }

        $code .= "use Duyler\\OpenApi\\Validator\\TypeFormatter;\n\n";
        $code .= sprintf("readonly class %s\n{\n", $shortName);
        $code .= '    public function validate(mixed $data): void' . "\n";
        $code .= "    {\n";

        if (null !== $schema->type) {
            $code .= match (true) {
                'array' === $schema->type => $this->generateArrayCheck($schema),
                'object' === $schema->type && null !== $schema->properties => $this->generateNestedObjectCheck($schema),
                default => $this->generateTypeCheck($schema->type),
            };
        }

        if (null !== $schema->enum) {
            $code .= $this->generateEnumCheck($schema->enum);
        }

        if (null !== $schema->minLength || null !== $schema->maxLength) {
            $code .= $this->generateStringLengthCheck($schema);
        }

        if (null !== $schema->minimum || null !== $schema->maximum) {
            $code .= $this->generateNumberRangeCheck($schema);
        }

        if (null !== $schema->pattern) {
            $code .= $this->generatePatternCheck($schema->pattern);
        }

        if ($schema->hasConst) {
            $code .= $this->generateConstCheck($schema->const);
        }

        if (null !== $schema->multipleOf) {
            $code .= $this->generateMultipleOfCheck($schema->multipleOf);
        }

        $code .= "    }\n";
        $code .= "}\n";

        return $code;
    }

    private function generateTypeCheck(string|array $type): string
    {
        $code = '';

        /** @var array<string|null> $rawTypes */
        $rawTypes = is_array($type) ? $type : [$type];

        /** @var list<string> $types */
        $types = array_values(array_filter(
            $rawTypes,
            static fn(mixed $t): bool => null !== $t,
        ));

        $checks = [];
        foreach ($types as $t) {
            $checks[] = $this->buildTypeCheckExpression($t, '$data');
        }

        if ([] === $checks) {
            return '';
        }

        $escapedTypes = array_map(
            static fn(string $t): string => var_export($t, true),
            $types,
        );
        $typesString = implode(" . '|' . ", $escapedTypes);

        $condition = 1 === count($checks)
            ? $checks[0]
            : '(' . implode(' || ', $checks) . ')';

        $code .= sprintf("        if (false === %s) {\n", $condition);
        $code .= sprintf("            throw new \\RuntimeException('Type mismatch: expected ' . %s . ' but got ' . TypeFormatter::format(\$data));\n", $typesString);
        $code .= "        }\n\n";

        return $code;
    }

    private function generateEnumCheck(array $enum): string
    {
        $enumValues = array_map(fn($val) => var_export($val, true), $enum);
        $valuesString = implode(', ', $enumValues);

        $code = "        if (false === in_array(\$data, [$valuesString], true)) {\n";
        $code .= "            throw new \\RuntimeException('Value must be one of: ' . implode(', ', [$valuesString]));\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function generateStringLengthCheck(Schema $schema): string
    {
        $conditions = [];

        if (null !== $schema->minLength) {
            $conditions[] = sprintf('$utf16Length < %d', $schema->minLength);
        }

        if (null !== $schema->maxLength) {
            $conditions[] = sprintf('$utf16Length > %d', $schema->maxLength);
        }

        $condition = implode(' || ', $conditions);
        $code = $this->generateUtf16LengthComputation();
        $code .= sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('String length validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    /**
     * Inlines UTF-16 code-unit counting into compiled validators so they
     * stay standalone (no dependency on the runtime Utf16 helper) while
     * still matching JSON Schema 2020-12 §6.3.1: supplementary characters
     * (4-byte UTF-8 sequences) count as 2 code units via surrogate pairs.
     */
    private function generateUtf16LengthComputation(): string
    {
        $code = "        \$utf16Length = 0;\n";
        $code .= "        \$utf16Bytes = strlen((string) \$data);\n";
        $code .= "        \$utf16Pos = 0;\n";
        $code .= "        while (\$utf16Pos < \$utf16Bytes) {\n";
        $code .= "            \$utf16Octet = ord(\$data[\$utf16Pos]);\n";
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

    private function generateNumberRangeCheck(Schema $schema): string
    {
        $code = '';
        $conditions = [];

        if (null !== $schema->minimum) {
            $conditions[] = sprintf('$data < %F', $schema->minimum);
        }

        if (null !== $schema->maximum) {
            $conditions[] = sprintf('$data > %F', $schema->maximum);
        }

        if (null !== $schema->exclusiveMinimum) {
            $conditions[] = sprintf('$data <= %F', $schema->exclusiveMinimum);
        }

        if (null !== $schema->exclusiveMaximum) {
            $conditions[] = sprintf('$data >= %F', $schema->exclusiveMaximum);
        }

        $condition = implode(' || ', $conditions);
        $code .= sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('Number range validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function generatePatternCheck(string $pattern): string
    {
        $normalizedPattern = new RegexValidator()->normalize($pattern);
        $escapedPattern = var_export($normalizedPattern, true);
        $code = sprintf("        if (false === preg_match(%s, (string) \$data)) {\n", $escapedPattern);
        $code .= "            throw new \\RuntimeException('Pattern validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function generateConstCheck(mixed $constValue): string
    {
        $exportedValue = var_export($constValue, true);

        $code = sprintf("        if (%s !== \$data) {\n", $exportedValue);
        $code .= sprintf("            throw new \\RuntimeException(sprintf('Value must be const: %%s', var_export(\$data, true)));\n");
        $code .= "        }\n\n";

        return $code;
    }

    private function generateMultipleOfCheck(float $multipleOf): string
    {
        if (0.0 === $multipleOf) {
            $errorMessage = var_export('multipleOf must be greater than 0', true);

            return sprintf("        throw new \\RuntimeException(%s);\n\n", $errorMessage);
        }

        $multipleOfStr = var_export($multipleOf, true);
        $errorMessage = sprintf('Value must be a multiple of %s', $multipleOfStr);
        $epsilonStr = var_export(self::RELATIVE_EPSILON_FACTOR, true);

        if ((float) (int) $multipleOf === $multipleOf) {
            $code = $this->generateIntegerMultipleOfCheck(
                intMultipleOf: (int) $multipleOf,
                floatMultipleOfStr: $multipleOfStr,
                epsilonStr: $epsilonStr,
                errorMessage: $errorMessage,
            );
        } else {
            $code = $this->buildFloatQuotientCheck(
                multipleOfStr: $multipleOfStr,
                epsilonStr: $epsilonStr,
                exportedMessage: var_export($errorMessage, true),
            );
        }

        return $code . "\n";
    }

    /**
     * Int-path: when multipleOf is a whole number, integer operands can use
     * the exact `%` modulus. Whole floats fall back to the float-path
     * because `(float) $data` may introduce rounding before the modulus.
     */
    private function generateIntegerMultipleOfCheck(
        int $intMultipleOf,
        string $floatMultipleOfStr,
        string $epsilonStr,
        string $errorMessage,
    ): string {
        $intMultipleOfStr = var_export($intMultipleOf, true);
        $exportedMessage = var_export($errorMessage, true);

        $code = "        if (is_int(\$data)) {\n";
        $code .= sprintf("            if (0 !== (\$data %% %s)) {\n", $intMultipleOfStr);
        $code .= sprintf("                throw new \\RuntimeException(%s);\n", $exportedMessage);
        $code .= "            }\n";
        $code .= "        } else {\n";
        $code .= $this->buildFloatQuotientCheck($floatMultipleOfStr, $epsilonStr, $exportedMessage);
        $code .= "        }\n";

        return $code;
    }

    /**
     * Float-path: quotient approach with a relative epsilon. Matches
     * NumericRangeValidator::isMultipleOf float branch exactly, avoiding
     * the precision-loss bug of fmod on large dividends.
     */
    private function buildFloatQuotientCheck(string $multipleOfStr, string $epsilonStr, string $exportedMessage): string
    {
        $code = sprintf("            \$quotient = (float) \$data / %s;\n", $multipleOfStr);
        $code .= "            \$rounded = round(\$quotient);\n";
        $code .= sprintf("            \$epsilon = %s * max(1.0, abs(\$quotient));\n", $epsilonStr);
        $code .= "            if (abs(\$quotient - \$rounded) >= \$epsilon) {\n";
        $code .= sprintf("                throw new \\RuntimeException(%s);\n", $exportedMessage);
        $code .= "            }\n";

        return $code;
    }

    private function generateAdditionalPropertiesCheck(Schema $schema, string $dataVar = '$data'): string
    {
        if (null === $schema->properties) {
            return '';
        }

        $allowedKeys = array_keys($schema->properties);
        $exportedKeys = var_export($allowedKeys, true);

        $code = sprintf("        foreach (array_keys(%s) as \$key) {\n", $dataVar);
        $code .= sprintf("            if (false === in_array(\$key, %s, true)) {\n", $exportedKeys);
        $code .= "                throw new \\RuntimeException('Additional property not allowed: ' . \$key);\n";
        $code .= "            }\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function getTypeCheckFunction(string $type): string
    {
        return match ($type) {
            'string' => 'is_string',
            'boolean' => 'is_bool',
            'array' => 'is_array',
            'object' => 'is_array',
            'null' => 'is_null',
            default => 'is_string',
        };
    }

    private function buildTypeCheckExpression(string $type, string $variable): string
    {
        if ('number' === $type) {
            return sprintf('(is_float(%1$s) || is_int(%1$s))', $variable);
        }

        if ('integer' === $type) {
            return sprintf(
                '(is_int(%1$s) || (is_float(%1$s) && 0.0 === fmod(%1$s, 1.0) && !is_infinite(%1$s) && !is_nan(%1$s)))',
                $variable,
            );
        }

        $function = $this->getTypeCheckFunction($type);

        return sprintf('%s(%s)', $function, $variable);
    }

    private function generateNestedObjectCheck(Schema $schema, string $dataVar = '$data'): string
    {
        if (null === $schema->properties) {
            return '';
        }

        $safeVarForError = addslashes($dataVar);
        $code = sprintf("        if (false === is_array(%s)) {\n", $dataVar);
        $code .= sprintf("            throw new \\RuntimeException('Expected object for %s');\n", $safeVarForError);
        $code .= "        }\n\n";

        foreach ($schema->properties as $propertyName => $propertySchema) {
            $code .= $this->generatePropertyValidation($propertySchema, (string) $propertyName, $dataVar);
        }

        if (null !== $schema->required && [] !== $schema->required) {
            $code .= $this->generateRequiredCheck($schema->required, $dataVar);
        }

        if (false === $schema->additionalProperties) {
            $code .= $this->generateAdditionalPropertiesCheck($schema, $dataVar);
        }

        return $code;
    }

    private function generatePropertyValidation(
        Schema $propertySchema,
        string $propertyName,
        string $dataVar,
    ): string {
        $code = '';

        if (null === $propertySchema->type) {
            return $code;
        }

        $safePropertyName = var_export($propertyName, true);
        $propertyVar = $dataVar . '[' . $safePropertyName . ']';

        $code .= sprintf("        if (isset(%s)) {\n", $propertyVar);

        $code .= ('object' === $propertySchema->type && null !== $propertySchema->properties)
            ? $this->generateNestedObjectCheck($propertySchema, $propertyVar)
            : $this->generateTypeCheckForValue($propertySchema->type, $propertyVar);

        $code .= "        }\n\n";

        return $code;
    }

    /**
     * @param list<string> $required
     */
    private function generateRequiredCheck(array $required, string $dataVar): string
    {
        $code = '';
        foreach ($required as $propertyName) {
            $safeName = var_export($propertyName, true);
            $code .= sprintf("        if (false === array_key_exists(%s, %s)) {\n", $safeName, $dataVar);
            $code .= sprintf(
                "            throw new \\RuntimeException('Required property missing: %s');\n",
                addslashes($propertyName),
            );
            $code .= "        }\n";
        }

        return $code . "\n";
    }

    private function generateTypeCheckForValue(string|array $type, string $valueVar): string
    {
        /** @var array<string|null> $rawTypes */
        $rawTypes = is_array($type) ? $type : [$type];

        /** @var list<string> $types */
        $types = array_values(array_filter(
            $rawTypes,
            static fn(mixed $t): bool => null !== $t,
        ));

        $checks = [];

        foreach ($types as $t) {
            $checks[] = $this->buildTypeCheckExpression($t, $valueVar);
        }

        if ([] === $checks) {
            return '';
        }

        $safeVarName = addslashes($valueVar);

        $condition = 1 === count($checks)
            ? $checks[0]
            : '(' . implode(' || ', $checks) . ')';

        $code = sprintf("        if (false === %s) {\n", $condition);
        $code .= sprintf("            throw new \\RuntimeException('Type mismatch for %s');\n", $safeVarName);
        $code .= "        }\n";

        return $code;
    }

    private function generateArrayCheck(Schema $schema): string
    {
        if (null === $schema->items) {
            return '';
        }

        $code = "        if (false === is_array(\$data)) {\n";
        $code .= "            throw new \\RuntimeException('Expected array');\n";
        $code .= "        }\n\n";

        if (null !== $schema->minItems) {
            $code .= sprintf("        if (count(\$data) < %d) {\n", $schema->minItems);
            $code .= "            throw new \\RuntimeException('Array too short');\n";
            $code .= "        }\n\n";
        }

        if (null !== $schema->maxItems) {
            $code .= sprintf("        if (count(\$data) > %d) {\n", $schema->maxItems);
            $code .= "            throw new \\RuntimeException('Array too long');\n";
            $code .= "        }\n\n";
        }

        if (true === $schema->uniqueItems) {
            $code .= "        \$__seen = [];\n";
            $code .= "        foreach (\$data as \$__item) {\n";
            $code .= "            \$__key = json_encode(\$__item, JSON_THROW_ON_ERROR);\n";
            $code .= "            if (in_array(\$__key, \$__seen, true)) {\n";
            $code .= "                throw new \\RuntimeException('Array items must be unique');\n";
            $code .= "            }\n";
            $code .= "            \$__seen[] = \$__key;\n";
            $code .= "        }\n\n";
        }

        $code .= $this->generateItemValidation($schema->items);

        return $code;
    }

    private function generateItemValidation(Schema $itemSchema): string
    {
        $code = "        foreach (\$data as \$index => \$item) {\n";

        if ('object' === $itemSchema->type && null !== $itemSchema->properties) {
            $code .= $this->generateNestedObjectCheck($itemSchema, '$item');
        } elseif (null !== $itemSchema->type) {
            $code .= $this->generateTypeCheckForValue($itemSchema->type, '$item');
        }

        if (null !== $itemSchema->enum) {
            $code .= "            if (false === in_array(\$item, " . var_export($itemSchema->enum, true) . ", true)) {\n";
            $code .= "                throw new \\RuntimeException('Invalid enum value in array');\n";
            $code .= "            }\n";
        }

        $code .= "        }\n\n";

        return $code;
    }

    /**
     * @param list<string> $resolved
     *
     * @return array{Schema, list<string>}
     */
    private function resolveRefs(Schema $schema, OpenApiDocument $document, array $resolved = []): array
    {
        if (null !== $schema->ref) {
            if (in_array($schema->ref, $resolved, true)) {
                throw new RuntimeException(sprintf('Circular reference detected: %s', $schema->ref));
            }

            $resolved[] = $schema->ref;
            [$result,] = $this->resolveRef($schema->ref, $document, $resolved);

            return [$result, $resolved];
        }

        $resolvedProperties = null;
        if (null !== $schema->properties) {
            $resolvedProperties = [];
            foreach ($schema->properties as $name => $property) {
                [$propResult,] = $this->resolveRefs($property, $document, $resolved);
                $resolvedProperties[$name] = $propResult;
            }
        }

        $resolvedItems = null;
        if (null !== $schema->items) {
            [$resolvedItems,] = $this->resolveRefs($schema->items, $document, $resolved);
        }

        $result = $schema->withOverrides(
            properties: $resolvedProperties,
            items: $resolvedItems,
        );

        return [$result, $resolved];
    }

    /**
     * @param list<string> $resolved
     *
     * @return array{Schema, list<string>}
     */
    private function resolveRef(string $ref, OpenApiDocument $document, array $resolved): array
    {
        if (false === str_starts_with($ref, '#/components/schemas/')) {
            throw new RuntimeException(sprintf('Unsupported $ref: %s', $ref));
        }

        $schemaName = substr($ref, self::REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH);
        $schemas = $document->components?->schemas ?? [];

        if (false === isset($schemas[$schemaName])) {
            throw new RuntimeException(sprintf('Schema not found: %s', $schemaName));
        }

        return $this->resolveRefs($schemas[$schemaName], $document, $resolved);
    }

    /**
     * @return list<string>
     */
    private function detectUnsupportedKeywords(Schema $schema): array
    {
        $keywordMap = [
            'allOf' => null !== $schema->allOf,
            'anyOf' => null !== $schema->anyOf,
            'oneOf' => null !== $schema->oneOf,
            'not' => null !== $schema->not,
            'if' => null !== $schema->if,
            'then' => null !== $schema->then,
            'else' => null !== $schema->else,
            'patternProperties' => null !== $schema->patternProperties,
            'format' => null !== $schema->format,
            'minProperties' => null !== $schema->minProperties,
            'maxProperties' => null !== $schema->maxProperties,
            'additionalProperties' => $schema->additionalProperties instanceof Schema,
            'prefixItems' => null !== $schema->prefixItems,
        ];

        $detected = array_keys(array_filter($keywordMap));

        if (null !== $schema->properties) {
            foreach ($schema->properties as $propertySchema) {
                $detected = [...$detected, ...$this->detectUnsupportedKeywords($propertySchema)];
            }
        }

        if (null !== $schema->items) {
            $detected = [...$detected, ...$this->detectUnsupportedKeywords($schema->items)];
        }

        return array_values(array_unique($detected));
    }
}
