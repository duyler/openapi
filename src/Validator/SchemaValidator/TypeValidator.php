<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\TypeMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;

final readonly class TypeValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->type) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';

        if (is_array($schema->type)) {
            if (false === $this->isValidUnionType($data, $schema->type)) {
                throw new TypeMismatchError(
                    expected: implode('|', $schema->type),
                    actual: get_debug_type($data),
                    dataPath: $dataPath,
                    schemaPath: '/type',
                );
            }

            return;
        }

        if (false === $this->isValidType($data, $schema->type)) {
            throw new TypeMismatchError(
                expected: $schema->type,
                actual: get_debug_type($data),
                dataPath: $dataPath,
                schemaPath: '/type',
            );
        }
    }

    private function isValidType(mixed $data, string $type): bool
    {
        return match ($type) {
            'string' => is_string($data),
            'number' => is_float($data) || is_int($data),
            'integer' => is_int($data),
            'boolean' => is_bool($data),
            'null' => null === $data,
            'array' => is_array($data) && array_is_list($data),
            'object' => is_array($data) && false === array_is_list($data),
            default => true,
        };
    }

    /**
     * @param array<int, string> $types
     */
    private function isValidUnionType(mixed $data, array $types): bool
    {
        return array_any($types, fn($type) => $this->isValidType($data, $type));
    }
}
