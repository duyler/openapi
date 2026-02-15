<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\RequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function array_key_exists;
use function is_array;

readonly class RequiredValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->required || [] === $schema->required) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $dataPath = $this->getDataPath($context);
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
