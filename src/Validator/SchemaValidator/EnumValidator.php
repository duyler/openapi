<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Override;

final readonly class EnumValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->enum || [] === $schema->enum) {
            return;
        }
        $found = array_any($schema->enum, fn($value) => $data === $value);

        if (false === $found) {
            $dataPath = $this->getDataPath($context);
            throw new EnumError(
                allowedValues: $schema->enum,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/enum',
            );
        }
    }
}
