<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\ValidatorMode;
use Override;

use function array_key_exists;
use function is_array;
use function sprintf;

final readonly class ReadOnlyWriteOnlyValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (null === $context || null === $context->mode) {
            return;
        }

        if (null === $schema->properties || [] === $schema->properties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        foreach ($schema->properties as $name => $propertySchema) {
            if (false === array_key_exists($name, $data)) {
                continue;
            }

            if (ValidatorMode::Request === $context->mode && $propertySchema->readOnly) {
                throw new ValidationException(
                    sprintf(
                        'Property "%s" is read-only and must not be sent in a request',
                        $name,
                    ),
                );
            }

            if (ValidatorMode::Response === $context->mode && $propertySchema->writeOnly) {
                throw new ValidationException(
                    sprintf(
                        'Property "%s" is write-only and must not be received in a response',
                        $name,
                    ),
                );
            }
        }
    }
}
