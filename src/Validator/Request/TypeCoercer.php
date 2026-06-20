<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Request;

use Duyler\OpenApi\Schema\Model\Parameter;
use Duyler\OpenApi\Validator\Coercion\AbstractCoercer;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;

use function is_array;
use function is_float;
use function is_int;
use function is_object;
use function is_string;
use function in_array;
use function get_object_vars;
use function is_bool;

final readonly class TypeCoercer extends AbstractCoercer
{
    /**
     * @return array<array-key, mixed>|int|string|float|bool|null
     */
    public function coerce(mixed $value, Parameter $param, bool $enabled, bool $strict = false): array|int|string|float|bool|null
    {
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

        $schema = $param->schema;

        if (null === $schema->type) {
            return $this->normalizeValue($value);
        }

        if (is_array($schema->type)) {
            return $this->coerceUnionType($value, $schema->type, $strict);
        }

        return $this->coerceToType($value, $schema->type, $strict);
    }

    /**
     * @param array<int, string> $types
     * @return array<array-key, mixed>|int|string|float|bool
     */
    private function coerceUnionType(mixed $value, array $types, bool $strict): array|int|string|float|bool
    {
        foreach ($types as $type) {
            if ('null' === $type) {
                continue;
            }

            $coerced = $this->coerceToType($value, $type, $strict);

            if ($this->isValidType($coerced, $type)) {
                return $coerced;
            }
        }

        return $this->normalizeValue($value);
    }

    /**
     * @return array<array-key, mixed>|int|string|float|bool
     */
    private function coerceToType(mixed $value, string $type, bool $strict): array|int|string|float|bool
    {
        if (is_string($value)) {
            /** @var int|float|bool|string */
            return match ($type) {
                'integer' => $this->coerceToInteger($value, $strict),
                'number' => $this->coerceToNumber($value, $strict),
                'boolean' => $this->coerceToBoolean($value, $strict),
                default => $value,
            };
        }

        return $this->normalizeValue($value);
    }

    /**
     * @return array<array-key, mixed>|int|string|float|bool
     */
    private function normalizeValue(mixed $value): array|int|string|float|bool
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_int($value) || is_string($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        if (is_object($value)) {
            return get_object_vars($value);
        }

        return (string) $value;
    }
}
