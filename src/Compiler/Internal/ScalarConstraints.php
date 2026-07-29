<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Compiler\Internal;

use Duyler\OpenApi\Schema\Model\Schema;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function implode;
use function is_array;
use function sprintf;
use function var_export;

/**
 * Emits scalar-constraint codegen (type, enum, const, string length,
 * numeric range, pattern, multipleOf) for the ValidatorCompiler. The
 * collaborator holds no mutable state and is constructed fresh per
 * compile() call. It delegates UTF-16 length computation to Utf16Length
 * and pattern matching to PatternCheck.
 *
 * @internal
 */
final readonly class ScalarConstraints
{
    private const float RELATIVE_EPSILON_FACTOR = 1e-9;

    public function __construct(
        private Utf16Length $utf16Length,
        private PatternCheck $patternCheck,
    ) {}

    /**
     * Emits all scalar-constraint code for the given schema in the canonical
     * order: type, enum, const, length, numeric range, pattern, multipleOf.
     */
    public function generate(Schema $schema, string $dataVar): string
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
            $code .= $this->patternCheck->generate($schema->pattern, $dataVar);
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

    private function generateTypeCheckForValue(string|array $type, string $valueVar): string
    {
        /** @var list<string> $types */
        $types = array_values(array_filter(
            is_array($type) ? $type : [$type],
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
        $code = $this->utf16Length->generate($valueVar);
        $code .= sprintf("        if (%s) {\n", $condition);
        $code .= "            throw new \\RuntimeException('String length validation failed');\n";
        $code .= "        }\n\n";

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
            return $this->generateIntegerMultipleOf(
                new MultipleOfContext(
                    intMultipleOf: (int) $multipleOf,
                    floatMultipleOfStr: $multipleOfStr,
                    epsilonStr: $epsilonStr,
                    errorMessage: $errorMessage,
                    valueVar: $valueVar,
                ),
            );
        }

        return $this->buildFloatQuotient(
            new FloatQuotientContext(
                multipleOfStr: $multipleOfStr,
                epsilonStr: $epsilonStr,
                exportedMessage: var_export($errorMessage, true),
                valueVar: $valueVar,
            ),
        ) . "\n";
    }

    private function generateIntegerMultipleOf(MultipleOfContext $context): string
    {
        $intMultipleOfStr = var_export($context->intMultipleOf, true);
        $exportedMessage = var_export($context->errorMessage, true);

        $code = sprintf("        if (is_int(%s)) {\n", $context->valueVar);
        $code .= sprintf("            if (0 !== (%s %% %s)) {\n", $context->valueVar, $intMultipleOfStr);
        $code .= sprintf("                throw new \\RuntimeException(%s);\n", $exportedMessage);
        $code .= "            }\n";
        $code .= "        } else {\n";
        $code .= $this->buildFloatQuotient(
            new FloatQuotientContext(
                multipleOfStr: $context->floatMultipleOfStr,
                epsilonStr: $context->epsilonStr,
                exportedMessage: $exportedMessage,
                valueVar: $context->valueVar,
            ),
        );
        $code .= "        }\n";

        return $code;
    }

    private function buildFloatQuotient(FloatQuotientContext $context): string
    {
        $code = sprintf("            \$quotient = (float) %s / %s;\n", $context->valueVar, $context->multipleOfStr);
        $code .= "            \$rounded = round(\$quotient);\n";
        $code .= sprintf("            \$epsilon = %s * max(1.0, abs(\$quotient));\n", $context->epsilonStr);
        $code .= "            if (abs(\$quotient - \$rounded) >= \$epsilon) {\n";
        $code .= sprintf("                throw new \\RuntimeException(%s);\n", $context->exportedMessage);
        $code .= "            }\n";

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
}
