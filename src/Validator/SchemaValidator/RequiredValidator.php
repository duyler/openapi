<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorPool;
use Override;

final readonly class RequiredValidator implements SchemaValidatorInterface
{
    public function __construct(
        private readonly ValidatorPool $pool,
    ) {}
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->required || [] === $schema->required) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $dataPath = null !== $context ? $context->breadcrumbs->currentPath() : '/';
        $errors = [];

        foreach ($schema->required as $field) {
            if (false === array_key_exists($field, $data)) {
                $errors[] = new RequiredError(
                    property: $field,
                    dataPath: $dataPath,
                    schemaPath: '/required',
                );
            }
        }

        if ([] !== $errors) {
            throw new ValidationException(
                'Missing required properties: ' . implode(', ', array_map(fn($e) => $e->getMessage(), $errors)),
                errors: $errors,
            );
        }
    }
}
