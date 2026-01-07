<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Override;

class MissingDiscriminatorPropertyException extends AbstractValidationError
{
    public function __construct(
        string $propertyName,
        string $dataPath = '/',
        string $schemaPath = '/discriminator',
    ) {
        $message = sprintf(
            'Missing required discriminator property "%s"',
            $propertyName,
        );

        parent::__construct(
            message: $message,
            keyword: 'discriminator',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [
                'propertyName' => $propertyName,
            ],
            suggestion: sprintf('Add property "%s" to the object', $propertyName),
        );
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
