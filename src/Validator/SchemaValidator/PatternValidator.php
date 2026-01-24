<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\PatternMismatchError;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

use function is_string;

final readonly class PatternValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === is_string($data) || null === $schema->pattern || '' === $schema->pattern) {
            return;
        }

        $result = preg_match($schema->pattern, $data);

        if (false === $result) {
            return;
        }

        if (0 === $result) {
            $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
            throw new PatternMismatchError(
                pattern: $schema->pattern,
                dataPath: $dataPath,
                schemaPath: '/pattern',
            );
        }
    }
}
