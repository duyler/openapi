<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\EnumError;
use Override;

use function in_array;

final readonly class EnumValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->enum || [] === $schema->enum) {
            return;
        }
        if (false === in_array($data, $schema->enum, true)) {
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
