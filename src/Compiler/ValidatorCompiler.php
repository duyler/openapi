<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use InvalidArgumentException;
use RuntimeException;

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
use function is_bool;
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

    private const float RELATIVE_EPSILON_FACTOR = 1e-9;

    private const int COMPILED_MAX_BACKTRACKS = 10_000;

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

        $code .= $this->generateConstraintsForSchema($schema, '$data', 0);

        $code .= "    }\n";

        if ($this->needsEqualityHelpers($schema)) {
            $code .= $this->renderJsonEqualsInline();
            $code .= $this->renderCanonicalKeyInline();
        }

        $code .= "}\n";

        return $code;
    }

    private function generateConstraintsForSchema(Schema $schema, string $dataVar, int $itemDepth): string
    {
        $code = $this->generateScalarConstraints($schema, $dataVar);
        $code .= $this->generateArrayLengthConstraints($schema, $dataVar);
        $code .= $this->generateObjectConstraints($schema, $dataVar, $itemDepth);

        return $code;
    }

    private function generateScalarConstraints(Schema $schema, string $dataVar): string
    {
        $code = '';

        if (null !== $schema->type) {
            $code .= $this->generateTypeCheckForValue($schema->type, $dataVar);
        }

        if (null !== $schema->enum) {
            $code .= $this->generateEnumCheck($schema->enum, $dataVar);
        }

        if ($schema->hasConst) {
            $code .= $this->generateConstCheck($schema->const, $dataVar);
        }

        if (null !== $schema->minLength || null !== $schema->maxLength) {
            $code .= $this->generateStringLengthCheck($schema, $dataVar);
        }

        if ($this->hasNumericRange($schema)) {
            $code .= $this->generateNumberRangeCheck($schema, $dataVar);
        }

        if (null !== $schema->pattern) {
            $code .= $this->generatePatternCheck($schema->pattern, $dataVar);
        }

        if (null !== $schema->multipleOf) {
            $code .= $this->generateMultipleOfCheck($schema->multipleOf, $dataVar);
        }

        return $code;
    }

    private function hasNumericRange(Schema $schema): bool
    {
        return null !== $schema->minimum
            || null !== $schema->maximum
            || null !== $schema->exclusiveMinimum
            || null !== $schema->exclusiveMaximum;
    }

    private function generateArrayLengthConstraints(Schema $schema, string $dataVar): string
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

        if (true === $schema->uniqueItems) {
            $code .= $this->generateUniqueItemsCheck($dataVar);
        }

        return $code;
    }

    private function generateObjectConstraints(Schema $schema, string $dataVar, int $itemDepth): string
    {
        $code = '';

        if (null !== $schema->required && [] !== $schema->required) {
            $code .= $this->generateRequiredCheck($schema->required, $dataVar);
        }

        if (false === $schema->additionalProperties) {
            $code .= $this->generateAdditionalPropertiesCheck($schema, $dataVar);
        }

        if (null !== $schema->properties) {
            $code .= $this->generatePropertiesConstraints($schema->properties, $dataVar, $itemDepth);
        }

        if ($schema->items instanceof Schema) {
            $code .= $this->generateItemsConstraints($schema->items, $dataVar, $itemDepth);
        }

        return $code;
    }

    /**
     * @param array<string, Schema> $properties
     */
    private function generatePropertiesConstraints(array $properties, string $dataVar, int $itemDepth): string
    {
        $code = '';

        foreach ($properties as $propertyName => $propertySchema) {
            $safePropertyName = var_export((string) $propertyName, true);
            $propertyVar = $dataVar . '[' . $safePropertyName . ']';
            $code .= sprintf("        if (isset(%s)) {\n", $propertyVar);
            $code .= $this->generateConstraintsForSchema($propertySchema, $propertyVar, $itemDepth);
            $code .= "        }\n\n";
        }

        return $code;
    }

    private function generateItemsConstraints(Schema $itemsSchema, string $dataVar, int $itemDepth): string
    {
        $itemVar = 0 === $itemDepth ? '$item' : '$__item_' . $itemDepth;
        $indexVar = 0 === $itemDepth ? '$index' : '$__index_' . $itemDepth;

        $code = sprintf("        foreach (%s as %s => %s) {\n", $dataVar, $indexVar, $itemVar);
        $code .= $this->generateConstraintsForSchema($itemsSchema, $itemVar, $itemDepth + 1);
        $code .= "        }\n\n";

        return $code;
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

        $escapedTypes = array_map(
            static fn(string $t): string => var_export($t, true),
            $types,
        );
        $typesString = implode(" . '|' . ", $escapedTypes);

        $condition = 1 === count($checks)
            ? $checks[0]
            : '(' . implode(' || ', $checks) . ')';

        $code = sprintf("        if (false === %s) {\n", $condition);
        $code .= sprintf(
            "            throw new \\RuntimeException('Type mismatch: expected ' . %s . ' but got ' . TypeFormatter::format(%s));\n",
            $typesString,
            $valueVar,
        );
        $code .= "        }\n\n";

        return $code;
    }

    /**
     * @param list<mixed> $enum
     */
    private function generateEnumCheck(array $enum, string $valueVar = '$data'): string
    {
        $enumValues = array_map(fn($val) => var_export($val, true), $enum);
        $valuesArray = '[' . implode(', ', $enumValues) . ']';

        $code = "        \$__matched = false;\n";
        $code .= sprintf("        foreach (%s as \$__candidate) {\n", $valuesArray);
        $code .= sprintf("            if (\$this->jsonEquals(\$__candidate, %s)) {\n", $valueVar);
        $code .= "                \$__matched = true;\n";
        $code .= "                break;\n";
        $code .= "            }\n";
        $code .= "        }\n";
        $code .= "        if (false === \$__matched) {\n";
        $code .= sprintf("            throw new \\RuntimeException('Value must be one of: ' . implode(', ', %s));\n", $valuesArray);
        $code .= "        }\n\n";

        return $code;
    }

    private function generateStringLengthCheck(Schema $schema, string $valueVar = '$data'): string
    {
        $conditions = [];

        if (null !== $schema->minLength) {
            $conditions[] = sprintf('$utf16Length < %d', $schema->minLength);
        }

        if (null !== $schema->maxLength) {
            $conditions[] = sprintf('$utf16Length > %d', $schema->maxLength);
        }

        $condition = implode(' || ', $conditions);
        $code = $this->generateUtf16LengthComputation($valueVar);
        $code .= sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('String length validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function generateUtf16LengthComputation(string $valueVar = '$data'): string
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

    private function generateNumberRangeCheck(Schema $schema, string $valueVar = '$data'): string
    {
        $conditions = [];

        if (null !== $schema->minimum) {
            $conditions[] = sprintf('%s < %F', $valueVar, $schema->minimum);
        }

        if (null !== $schema->maximum) {
            $conditions[] = sprintf('%s > %F', $valueVar, $schema->maximum);
        }

        if (null !== $schema->exclusiveMinimum) {
            $conditions[] = sprintf('%s <= %F', $valueVar, $schema->exclusiveMinimum);
        }

        if (null !== $schema->exclusiveMaximum) {
            $conditions[] = sprintf('%s >= %F', $valueVar, $schema->exclusiveMaximum);
        }

        $condition = implode(' || ', $conditions);
        $code = sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('Number range validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function generatePatternCheck(string $pattern, string $valueVar = '$data'): string
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

    private function generateConstCheck(mixed $constValue, string $valueVar = '$data'): string
    {
        $exportedValue = var_export($constValue, true);

        $code = sprintf("        if (false === \$this->jsonEquals(%s, %s)) {\n", $exportedValue, $valueVar);
        $code .= sprintf("            throw new \\RuntimeException(sprintf('Value must be const: %%s', var_export(%s, true)));\n", $valueVar);
        $code .= "        }\n\n";

        return $code;
    }

    private function generateMultipleOfCheck(float $multipleOf, string $valueVar = '$data'): string
    {
        if (0.0 === $multipleOf) {
            $errorMessage = var_export('multipleOf must be greater than 0', true);

            return sprintf("        throw new \\RuntimeException(%s);\n\n", $errorMessage);
        }

        $multipleOfStr = var_export($multipleOf, true);
        $errorMessage = sprintf('Value must be a multiple of %s', $multipleOfStr);
        $epsilonStr = var_export(self::RELATIVE_EPSILON_FACTOR, true);

        if ((float) (int) $multipleOf === $multipleOf) {
            return $this->generateIntegerMultipleOfCheck(
                intMultipleOf: (int) $multipleOf,
                floatMultipleOfStr: $multipleOfStr,
                epsilonStr: $epsilonStr,
                errorMessage: $errorMessage,
                valueVar: $valueVar,
            );
        }

        return $this->buildFloatQuotientCheck(
            multipleOfStr: $multipleOfStr,
            epsilonStr: $epsilonStr,
            exportedMessage: var_export($errorMessage, true),
            valueVar: $valueVar,
        ) . "\n";
    }

    private function generateIntegerMultipleOfCheck(
        int $intMultipleOf,
        string $floatMultipleOfStr,
        string $epsilonStr,
        string $errorMessage,
        string $valueVar = '$data',
    ): string {
        $intMultipleOfStr = var_export($intMultipleOf, true);
        $exportedMessage = var_export($errorMessage, true);

        $code = sprintf("        if (is_int(%s)) {\n", $valueVar);
        $code .= sprintf("            if (0 !== (%s %% %s)) {\n", $valueVar, $intMultipleOfStr);
        $code .= sprintf("                throw new \\RuntimeException(%s);\n", $exportedMessage);
        $code .= "            }\n";
        $code .= "        } else {\n";
        $code .= $this->buildFloatQuotientCheck($floatMultipleOfStr, $epsilonStr, $exportedMessage, $valueVar);
        $code .= "        }\n";

        return $code;
    }

    private function buildFloatQuotientCheck(
        string $multipleOfStr,
        string $epsilonStr,
        string $exportedMessage,
        string $valueVar = '$data',
    ): string {
        $code = sprintf("            \$quotient = (float) %s / %s;\n", $valueVar, $multipleOfStr);
        $code .= "            \$rounded = round(\$quotient);\n";
        $code .= sprintf("            \$epsilon = %s * max(1.0, abs(\$quotient));\n", $epsilonStr);
        $code .= "            if (abs(\$quotient - \$rounded) >= \$epsilon) {\n";
        $code .= sprintf("                throw new \\RuntimeException(%s);\n", $exportedMessage);
        $code .= "            }\n";

        return $code;
    }

    private function generateUniqueItemsCheck(string $valueVar = '$data'): string
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

    private function generateAdditionalPropertiesCheck(Schema $schema, string $dataVar = '$data'): string
    {
        if (null === $schema->properties) {
            return '';
        }

        $allowedKeys = array_keys($schema->properties);
        $exportedKeys = var_export($allowedKeys, true);

        $code = sprintf("        foreach (array_keys(%s) as \$key) {\n", $dataVar);
        $code .= sprintf("            if (false === in_array(\$key, %s, true)) {\n", $exportedKeys);
        $code .= "                throw new \\RuntimeException(sprintf('Additional property not allowed: %s', var_export(\$key, true)));\n";
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
                "            throw new \\RuntimeException(sprintf('Required property missing: %%s', %s));\n",
                $safeName,
            );
            $code .= "        }\n";
        }

        return $code . "\n";
    }

    private function renderJsonEqualsInline(): string
    {
        return <<<'PHP'
    private function jsonEquals(mixed $a, mixed $b): bool
    {
        if (is_bool($a) || is_bool($b)) {
            return $a === $b;
        }
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            if (is_int($a) && is_int($b)) {
                return $a === $b;
            }
            if (is_int($a) && abs($a) > 9007199254740992) {
                return false;
            }
            if (is_int($b) && abs($b) > 9007199254740992) {
                return false;
            }
            return (float) $a === (float) $b;
        }
        if (is_array($a) && is_array($b)) {
            return $this->arraysEqual($a, $b);
        }
        return $a === $b;
    }

    private function arraysEqual(array $a, array $b): bool
    {
        if (count($a) !== count($b)) {
            return false;
        }
        if (array_is_list($a) && array_is_list($b)) {
            for ($i = 0, $n = count($a); $i < $n; ++$i) {
                if (!$this->jsonEquals($a[$i], $b[$i])) {
                    return false;
                }
            }
            return true;
        }
        foreach (array_keys($a) as $__key) {
            if (!array_key_exists($__key, $b)) {
                return false;
            }
            if (!$this->jsonEquals($a[$__key], $b[$__key])) {
                return false;
            }
        }
        return true;
    }

PHP;
    }

    private function renderCanonicalKeyInline(): string
    {
        return <<<'PHP'
    private function canonicalJsonKey(mixed $value): string
    {
        if (is_float($value) && (float) (int) $value === $value) {
            $value = (int) $value;
        }
        if (is_array($value)) {
            $value = $this->canonicalizeArrayKeys($value);
        }
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function canonicalizeArrayKeys(array $value): array
    {
        ksort($value);
        foreach ($value as $k => $v) {
            if (is_array($v)) {
                $value[$k] = $this->canonicalizeArrayKeys($v);
            } elseif (is_float($v) && (float) (int) $v === $v) {
                $value[$k] = (int) $v;
            }
        }
        return $value;
    }

PHP;
    }

    private function needsEqualityHelpers(Schema $schema): bool
    {
        if ($schema->hasConst || null !== $schema->enum || true === $schema->uniqueItems) {
            return true;
        }

        if ($schema->items instanceof Schema && $this->needsEqualityHelpers($schema->items)) {
            return true;
        }

        if (null !== $schema->properties) {
            foreach ($schema->properties as $child) {
                if ($this->needsEqualityHelpers($child)) {
                    return true;
                }
            }
        }

        return false;
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
        if ($schema->items instanceof Schema) {
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
            'contains' => null !== $schema->contains,
            'propertyNames' => null !== $schema->propertyNames,
            'unevaluatedItems' => null !== $schema->unevaluatedItems,
            'unevaluatedProperties' => null !== $schema->unevaluatedProperties,
            'dependentSchemas' => null !== $schema->dependentSchemas,
            'discriminator' => null !== $schema->discriminator,
            'contentEncoding' => null !== $schema->contentEncoding,
            'contentMediaType' => null !== $schema->contentMediaType,
            'contentSchema' => null !== $schema->contentSchema,
            'items (boolean form)' => is_bool($schema->items),
            'contains (boolean form)' => is_bool($schema->contains),
            'propertyNames (boolean form)' => is_bool($schema->propertyNames),
            'if (boolean form)' => is_bool($schema->if),
            'then (boolean form)' => is_bool($schema->then),
            'else (boolean form)' => is_bool($schema->else),
            'not (boolean form)' => is_bool($schema->not),
            'unevaluatedItems (boolean form)' => is_bool($schema->unevaluatedItems),
        ];

        $detected = array_keys(array_filter($keywordMap));

        if (null !== $schema->properties) {
            foreach ($schema->properties as $propertySchema) {
                $detected = [...$detected, ...$this->detectUnsupportedKeywords($propertySchema)];
            }
        }

        if ($schema->items instanceof Schema) {
            $detected = [...$detected, ...$this->detectUnsupportedKeywords($schema->items)];
        }

        return array_values(array_unique($detected));
    }
}
