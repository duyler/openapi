<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\MaxLengthError;
use Duyler\OpenApi\Validator\Exception\MinLengthError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_string;

final readonly class StringLengthValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_string($data)) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
        $length = mb_strlen($data);

        if (null !== $schema->minLength && $length < $schema->minLength) {
            throw new MinLengthError(
                minLength: $schema->minLength,
                actualLength: $length,
                dataPath: $dataPath,
                schemaPath: '/minLength',
            );
        }

        if (null !== $schema->maxLength && $length > $schema->maxLength) {
            throw new MaxLengthError(
                maxLength: $schema->maxLength,
                actualLength: $length,
                dataPath: $dataPath,
                schemaPath: '/maxLength',
            );
        }
    }
}
