<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Schema\Model\Schema;
use Override;

use function count;
use function sprintf;

class UnknownDiscriminatorValueException extends AbstractValidationError
{
    public function __construct(
        string $value,
        Schema $schema,
        string $dataPath = '/',
        string $schemaPath = '/discriminator',
    ) {
        $mappingValues = [];
        if (null !== $schema->discriminator && null !== $schema->discriminator->mapping) {
            $mappingValues = array_keys($schema->discriminator->mapping);
        }

        $message = sprintf(
            'Unknown discriminator value "%s"',
            $value,
        );

        if (count($mappingValues) > 0) {
            $message .= sprintf(
                '. Known values: %s',
                implode(', ', $mappingValues),
            );
        }

        parent::__construct(
            message: $message,
            keyword: 'discriminator',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [
                'value' => $value,
                'mappingValues' => $mappingValues,
            ],
            suggestion: count($mappingValues) > 0
                ? sprintf('Use one of: %s', implode(', ', $mappingValues))
                : null,
        );
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
