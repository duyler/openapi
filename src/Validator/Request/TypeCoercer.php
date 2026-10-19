<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function get_object_vars;
use function in_array;
use function is_array;
use function is_object;
use function is_scalar;

final readonly class TypeCoercer extends AbstractCoercer
{
    /**
     * @return array<array-key, mixed>|int|string|float|bool|null
     *
     * Coerce $value according to $param's schema. When $enabled is false or the
     * schema has no type, the value is normalised without type conversion.
     *
     * Since TYPECOERCER-DEFAULT-STRICT the $strict argument defaults to true:
     * arbitrary strings no longer silently cast to boolean, and integer
     * overflow from string input is rejected. Internal callers always pass
     * $strict explicitly via ParameterValidationConfig::strictCoercion.
     * Third-party callers that want the legacy lax behaviour MUST pass false
     * explicitly. Note: the SEC-14 float-to-int overflow guard and the
     * SEC-15 numeric-string precision-loss guard run unconditionally in
     * both strict and non-strict modes.
     */
    public function coerce(
        mixed $value,
        Parameter $param,
        bool $enabled,
        bool $strict = true,
    ): array|int|string|float|bool|null {
        if (null === $value) {
            if (null !== $param->schema && null !== $param->schema->type) {
                $types = is_array($param->schema->type) ? $param->schema->type : [$param->schema->type];
                if (in_array('null', $types, true) || $param->schema->nullable) {
                    return null;
                }
            }

            throw new TypeMismatchError(
                expected: 'non-null',
                actual: 'null',
                dataPath: '',
                schemaPath: '/type',
            );
        }

        if (false === $enabled || null === $param->schema) {
            return $this->normalizeValue($value);
        }

        return $this->coerceBySchema($value, $param->schema, $strict);
    }

    private function coerceBySchema(mixed $value, Schema $schema, bool $strict): array|int|string|float|bool|null
    {
        if (null === $value) {
            return null;
        }

        if (null === $schema->type) {
            return $this->normalizeValue($value);
        }

        if (is_array($schema->type)) {
            return $this->coerceUnionType($value, $schema->type, $schema, $strict);
        }

        return $this->coerceToType($value, $schema->type, $schema, $strict);
    }

    /**
     * @param array<int, string> $types
     */
    private function coerceUnionType(mixed $value, array $types, Schema $schema, bool $strict): array|int|string|float|bool|null
    {
        foreach ($types as $type) {
            if ('null' === $type) {
                continue;
            }

            try {
                $coerced = $this->coerceToType($value, $type, $schema, $strict);
            } catch (TypeMismatchError) {
                continue;
            }

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        return $this->normalizeValue($value);
    }

    private function coerceToType(mixed $value, string $type, Schema $schema, bool $strict): array|int|string|float|bool|null
    {
        if (false === is_scalar($value) && false === is_array($value)) {
            return $this->normalizeValue($value);
        }

        $recurse = fn(mixed $nested, Schema $nestedSchema): array|int|string|float|bool|null
            => $this->coerceBySchema($nested, $nestedSchema, $strict);

        /** @var array<array-key, mixed>|int|string|float|bool|null */
        return match ($type) {
            'integer' => $strict ? $this->coerceToIntegerStrict($value) : $this->coerceToInteger($value),
            'number' => $strict ? $this->coerceToNumberStrict($value) : $this->coerceToNumber($value),
            'boolean' => $strict ? $this->coerceToBooleanStrict($value) : $this->coerceToBoolean($value),
            'string' => $this->coerceToString($value),
            'object' => $this->coerceDeclaredProperties($value, $schema, $recurse),
            'array' => $this->coerceDeclaredItems($value, $schema, $recurse),
            default => $this->normalizeValue($value),
        };
    }

    private function normalizeValue(mixed $value): array|int|string|float|bool
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return (string) $value;
    }
}
