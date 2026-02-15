<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\EmptyArrayStrategy;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Override;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

readonly class TypeValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->type) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        $nullableAsType = $context?->nullableAsType ?? true;

        if (null === $data && $schema->nullable && $nullableAsType) {
            return;
        }

        $emptyArrayStrategy = $context?->emptyArrayStrategy ?? EmptyArrayStrategy::AllowBoth;

        if (is_array($schema->type)) {
            if (false === $this->isValidUnionType($data, $schema->type, $emptyArrayStrategy)) {
                throw new TypeMismatchError(
                    expected: implode('|', $schema->type),
                    actual: get_debug_type($data),
                    dataPath: $dataPath,
                    schemaPath: '/type',
                );
            }

            return;
        }

        if (false === $this->isValidType($data, $schema->type, $emptyArrayStrategy)) {
            throw new TypeMismatchError(
                expected: $schema->type,
                actual: get_debug_type($data),
                dataPath: $dataPath,
                schemaPath: '/type',
            );
        }
    }

    private function isValidType(mixed $data, string $type, EmptyArrayStrategy $strategy): bool
    {
        return match ($type) {
            'string' => is_string($data),
            'number' => is_float($data) || is_int($data),
            'integer' => is_int($data),
            'boolean' => is_bool($data),
            'null' => null === $data,
            'array' => $this->isArray($data, $strategy),
            'object' => $this->isObject($data, $strategy),
            default => true,
        };
    }

    /**
     * @param array<int, string> $types
     */
    private function isValidUnionType(mixed $data, array $types, EmptyArrayStrategy $strategy): bool
    {
        return array_any($types, fn($type) => $this->isValidType($data, $type, $strategy));
    }

    private function isArray(mixed $data, EmptyArrayStrategy $strategy): bool
    {
        if (false === is_array($data)) {
            return false;
        }

        if ([] === $data) {
            return match ($strategy) {
                EmptyArrayStrategy::PreferArray => true,
                EmptyArrayStrategy::PreferObject => false,
                EmptyArrayStrategy::Reject => false,
                EmptyArrayStrategy::AllowBoth => true,
            };
        }

        return array_is_list($data);
    }

    private function isObject(mixed $data, EmptyArrayStrategy $strategy): bool
    {
        if (false === is_array($data)) {
            return false;
        }

        if ([] === $data) {
            return match ($strategy) {
                EmptyArrayStrategy::PreferArray => false,
                EmptyArrayStrategy::PreferObject => true,
                EmptyArrayStrategy::Reject => false,
                EmptyArrayStrategy::AllowBoth => true,
            };
        }

        return false === array_is_list($data);
    }
}
