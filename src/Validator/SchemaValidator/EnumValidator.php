<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class EnumValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->enum || [] === $schema->enum) {
            return;
        }
        $found = array_any($schema->enum, fn($value) => $data === $value);

        if (false === $found) {
            $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
            throw new EnumError(
                allowedValues: $schema->enum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/enum',
            );
        }
    }
}
