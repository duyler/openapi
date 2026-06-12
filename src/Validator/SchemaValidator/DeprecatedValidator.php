<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Event\ValidationWarningEvent;
use Duyler\OpenApi\Schema\Model\Schema;
use Duyler\OpenApi\Validator\Error\ValidationContext;
use Override;

use function array_key_exists;
use function is_array;
use function sprintf;

final readonly class DeprecatedValidator extends AbstractSchemaValidator
{
    #[Override]
    public function validate(mixed $data, Schema $schema, ?ValidationContext $context = null): void
    {
        if (false === $this->reportDeprecated) {
            return;
        }

        if (null === $schema->properties || [] === $schema->properties) {
            return;
        }

        if (false === is_array($data)) {
            return;
        }

        $dataPath = $this->getDataPath($context);

        foreach ($schema->properties as $name => $propertySchema) {
            if (false === $propertySchema->deprecated) {
                continue;
            }

            if (false === array_key_exists($name, $data)) {
                continue;
            }

            $propertyPath = '/' === $dataPath ? '/' . $name : $dataPath . '/' . $name;

            $this->eventDispatcher?->dispatch(
                new ValidationWarningEvent(
                    propertyPath: $propertyPath,
                    propertyName: $name,
                    message: sprintf('Property "%s" is deprecated', $name),
                ),
            );

            $this->logger->warning(
                sprintf('Deprecated property "%s" is used at path "%s"', $name, $propertyPath),
            );
        }
    }
}
