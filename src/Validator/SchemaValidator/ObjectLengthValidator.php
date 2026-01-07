<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaxPropertiesError;
use Duyler\OpenApi\Validator\Exception\MinPropertiesError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class ObjectLengthValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_array($data) || ([] !== $data && array_is_list($data))) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
        /** @var array<array-key, mixed> $data */
        $count = count($data);

        if (null !== $schema->minProperties && $count < $schema->minProperties) {
            throw new MinPropertiesError(
                minProperties: $schema->minProperties,
                actualCount: $count,
                dataPath: $dataPath,
                schemaPath: '/minProperties',
            );
        }

        if (null !== $schema->maxProperties && $count > $schema->maxProperties) {
            throw new MaxPropertiesError(
                maxProperties: $schema->maxProperties,
                actualCount: $count,
                dataPath: $dataPath,
                schemaPath: '/maxProperties',
            );
        }
    }
}
