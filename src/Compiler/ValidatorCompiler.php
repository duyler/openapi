<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler;

use Duyler\OpenApi\Compiler\Exception\UnsupportedKeywordException;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Schema\OpenApiDocument;
use Duyler\OpenApi\Validator\Schema\RegexValidator;
use RuntimeException;

use function array_keys;
use function count;
use function in_array;
use function is_array;
use function sprintf;

/**
 * @experimental
 */
final readonly class ValidatorCompiler
{
    private const int REF_COMPONENTS_SCHEMAS_PREFIX_LENGTH = 21;

    public function compile(Schema $schema, string $className): string
    {
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
        $resolvedSchema = $this->resolveRefs($schema, $document);
        return $this->compile($resolvedSchema, $className);
    }

    public function compileWithCache(
        Schema $schema,
        string $className,
        ?CompilationCache $cache = null,
    ): string {
        if (null !== $cache) {
            $schemaHash = $cache->generateKey($schema);
            $cached = $cache->get($schemaHash);

            if (null !== $cached) {
                return $cached;
            }
        }

        $code = $this->compile($schema, $className);

        if (null !== $cache) {
            $schemaHash = $cache->generateKey($schema);
            $cache->set($schemaHash, $code);
        }

        return $code;
    }

    private function generateCode(Schema $schema, string $className): string
    {
        $code = "<?php\n\n";
        $code .= sprintf("readonly class %s\n{\n", $className);
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

        $code .= "    }\n";
        $code .= "}\n";

        return $code;
    }

    private function generateTypeCheck(string|array $type): string
    {
        $code = '';

        /** @var array<string> $types */
        $types = is_array($type) ? $type : [$type];

        $checks = [];
        foreach ($types as $t) {
            $function = $this->getTypeCheckFunction($t);
            $checks[] = $this->buildTypeCheckExpression($function, '$data');
        }

        if (0 === count($checks)) {
            return '';
        }

        if (1 === count($checks)) {
            $code .= sprintf("        if (false === %s) {\n", $checks[0]);

            $typesString = implode('|', $types);
            $code .= sprintf("            throw new \\RuntimeException('Type mismatch: expected %s but got ' . get_debug_type(\$data));\n", $typesString);
            $code .= "        }\n\n";

            return $code;
        }

        $condition = implode(' && ', $checks);
        $code .= sprintf("        if (false === (%s)) {\n", $condition);

        $typesString = implode('|', $types);
        $code .= sprintf("            throw new \\RuntimeException('Type mismatch: expected %s but got ' . get_debug_type(\$data));\n", $typesString);
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
        $code = '';
        $conditions = [];

        if (null !== $schema->minLength) {
            $conditions[] = sprintf('mb_strlen($data, \'UTF-8\') < %d', $schema->minLength);
        }

        if (null !== $schema->maxLength) {
            $conditions[] = sprintf('mb_strlen($data, \'UTF-8\') > %d', $schema->maxLength);
        }

        if ([] === $conditions) {
            return '';
        }

        $condition = implode(' || ', $conditions);
        $code .= sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('String length validation failed');\n";
        $code .= "        }\n\n";

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

        if ([] === $conditions) {
            return '';
        }

        $condition = implode(' || ', $conditions);
        $code .= sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('Number range validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function generatePatternCheck(string $pattern): string
    {
        $normalizedPattern = RegexValidator::normalize($pattern);
        $escapedPattern = var_export($normalizedPattern, true);
        $code = sprintf("        if (false === preg_match(%s, (string) \$data)) {\n", $escapedPattern);
        $code .= "            throw new \\RuntimeException('Pattern validation failed');\n";
        $code .= "        }\n\n";

        return $code;
    }

    private function getTypeCheckFunction(string $type): string
    {
        return match ($type) {
            'string' => 'is_string',
            'number' => 'is_number',
            'integer' => 'is_int',
            'boolean' => 'is_bool',
            'array' => 'is_array',
            'object' => 'is_array',
            'null' => 'is_null',
            default => 'is_string',
        };
    }

    private function generateNestedObjectCheck(Schema $schema, string $dataVar = '$data'): string
    {
        if (null === $schema->properties) {
            return '';
        }

        $escapedVar = '$data' === $dataVar ? "\$data" : $dataVar;
        $code = sprintf("        if (false === is_array(%s)) {\n", $escapedVar);
        $code .= sprintf("            throw new \\RuntimeException('Expected object for %s');\n", $escapedVar);
        $code .= "        }\n\n";

        foreach ($schema->properties as $propertyName => $propertySchema) {
            $propertyVar = sprintf('%s[\'%s\']', $dataVar, $propertyName);
            $code .= $this->generatePropertyValidation($propertySchema, $propertyName, $propertyVar);
        }

        if (null !== $schema->required && [] !== $schema->required) {
            $code .= $this->generateRequiredCheck($schema->required, $dataVar);
        }

        return $code;
    }

    private function generatePropertyValidation(
        Schema $propertySchema,
        string $propertyName,
        string $propertyVar,
    ): string {
        $code = '';

        if (null === $propertySchema->type) {
            return $code;
        }

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
            $propertyNameStr = (string) $propertyName;
            $code .= sprintf("        if (false === array_key_exists('%s', %s)) {\n", $propertyNameStr, $dataVar);
            $code .= sprintf("            throw new \\RuntimeException('Required property missing: %s');\n", $propertyNameStr);
            $code .= "        }\n";
        }

        return $code . "\n";
    }

    private function generateTypeCheckForValue(string|array $type, string $valueVar): string
    {
        /** @var array<string> $types */
        $types = is_array($type) ? $type : [$type];
        $checks = [];

        foreach ($types as $t) {
            $function = $this->getTypeCheckFunction($t);
            $checks[] = $this->buildTypeCheckExpression($function, $valueVar);
        }

        if (0 === count($checks)) {
            return '';
        }

        if (1 === count($checks)) {
            $code = sprintf("        if (false === %s) {\n", $checks[0]);
            $code .= sprintf("            throw new \\RuntimeException('Type mismatch for %s');\n", $valueVar);
            $code .= "        }\n";

            return $code;
        }

        $condition = implode(' && ', $checks);
        $code = sprintf("        if (false === (%s)) {\n", $condition);
        $code .= sprintf("            throw new \\RuntimeException('Type mismatch for %s');\n", $valueVar);
        $code .= "        }\n";

        return $code;
    }

    private function buildTypeCheckExpression(string $function, string $variable): string
    {
        if ('is_number' === $function) {
            return sprintf('(is_float(%s) || is_int(%s))', $variable, $variable);
        }

        return sprintf('%s(%s)', $function, $variable);
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

        if (null !== $schema->uniqueItems && $schema->uniqueItems) {
            $code .= "        if (count(\$data) !== count(array_unique(\$data, SORT_REGULAR))) {\n";
            $code .= "            throw new \\RuntimeException('Array items must be unique');\n";
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

    private function resolveRefs(Schema $schema, OpenApiDocument $document, array &$resolved = []): Schema
    {
        if (null !== $schema->ref) {
            if (in_array($schema->ref, $resolved, true)) {
                throw new RuntimeException(sprintf('Circular reference detected: %s', $schema->ref));
            }

            $resolved[] = $schema->ref;
            $result = $this->resolveRef($schema->ref, $document, $resolved);
            array_pop($resolved);

            return $result;
        }

        $resolvedProperties = null;
        if (null !== $schema->properties) {
            $resolvedProperties = [];
            foreach ($schema->properties as $name => $property) {
                $resolvedProperties[$name] = $this->resolveRefs($property, $document, $resolved);
            }
        }

        $resolvedItems = null;
        if (null !== $schema->items) {
            $resolvedItems = $this->resolveRefs($schema->items, $document, $resolved);
        }

        return new Schema(
            type: $schema->type,
            enum: $schema->enum,
            default: $schema->default,
            hasDefault: $schema->hasDefault,
            const: $schema->const,
            hasConst: $schema->hasConst,
            nullable: $schema->nullable,
            minLength: $schema->minLength,
            maxLength: $schema->maxLength,
            minimum: $schema->minimum,
            maximum: $schema->maximum,
            exclusiveMinimum: $schema->exclusiveMinimum,
            exclusiveMaximum: $schema->exclusiveMaximum,
            multipleOf: $schema->multipleOf,
            pattern: $schema->pattern,
            minItems: $schema->minItems,
            maxItems: $schema->maxItems,
            uniqueItems: $schema->uniqueItems,
            minProperties: $schema->minProperties,
            maxProperties: $schema->maxProperties,
            required: $schema->required,
            properties: $resolvedProperties,
            additionalProperties: $schema->additionalProperties,
            items: $resolvedItems,
            ref: null,
        );
    }

    private function resolveRef(string $ref, OpenApiDocument $document, array &$resolved): Schema
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
