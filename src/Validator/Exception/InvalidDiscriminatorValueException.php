<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Override;

class InvalidDiscriminatorValueException extends AbstractValidationError
{
    public function __construct(
        string $propertyName,
        string $expectedType,
        string $actualType,
        string $dataPath = '/',
        string $schemaPath = '/discriminator',
    ) {
        $message = sprintf(
            'Discriminator property "%s" must be %s, but got %s',
            $propertyName,
            $expectedType,
            $actualType,
        );

        parent::__construct(
            message: $message,
            keyword: 'discriminator',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [
                'propertyName' => $propertyName,
                'expectedType' => $expectedType,
                'actualType' => $actualType,
            ],
        );
    }

    #[Override]
    public function __toString(): string
    {
        return $this->getMessage();
    }
}
