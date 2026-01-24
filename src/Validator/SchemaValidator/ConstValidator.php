<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ConstError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class ConstValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->const) {
            return;
        }

        if ($data !== $schema->const) {
            $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
            throw new ConstError(
                expected: $schema->const,
                actual: $data,
                dataPath: $dataPath,
                schemaPath: '/const',
            );
        }
    }
}
