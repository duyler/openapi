<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\DependentRequiredError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Override;

use function array_key_exists;
use function array_map;
use function implode;
use function is_array;

final readonly class DependentRequiredValidator extends AbstractSchemaValidator implements KeywordApplicable
{
    #[Override]
    public function isApplicable(Schema $schema): bool
    {
        return null !== $schema->dependentRequired && [] !== $schema->dependentRequired;
    }

    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $schema->dependentRequired || [] === $schema->dependentRequired) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $dataPath = $this->getDataPath($context);
        $errors = [];

        foreach ($schema->dependentRequired as $triggerProperty => $requiredProperties) {
            if (false === array_key_exists($triggerProperty, $data)) {
                continue;
            }

            foreach ($requiredProperties as $requiredProperty) {
                if (false === array_key_exists($requiredProperty, $data)) {
                    $errors[] = new DependentRequiredError(
                        property: $requiredProperty,
                        triggerProperty: $triggerProperty,
                        dataPath: $dataPath,
                        schemaPath: '/dependentRequired/' . $triggerProperty,
                    );
                }
            }
        }

        if ([] !== $errors) {
            throw new ValidationException(
                'Missing dependent required properties: ' . implode(', ', array_map(fn($e) => $e->getMessage(), $errors)),
                errors: $errors,
            );
        }
    }
}
