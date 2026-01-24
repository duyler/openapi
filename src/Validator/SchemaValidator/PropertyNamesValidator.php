<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_array;

final readonly class PropertyNamesValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->propertyNames) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        foreach (array_keys($data) as $propertyName) {
            $validator = new SchemaValidator($this->pool);
            $validator->validate($propertyName, $schema->propertyNames, $context);
        }
    }
}
