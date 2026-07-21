<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class DiscriminatorMismatchException extends AbstractValidationError
{
    public function __construct(
        string $expectedType,
        string $actualType,
        string $propertyName,
        string $dataPath = '/',
        string $schemaPath = '/discriminator',
    ) {
        $message = sprintf(
            'Discriminator expects %s at property "%s", but got %s',
            $expectedType,
            $propertyName,
            $actualType,
        );

        parent::__construct(
            message: $message,
            keyword: 'discriminator',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: [
                'expectedType' => $expectedType,
                'actualType' => $actualType,
                'propertyName' => $propertyName,
            ],
        );
    }
}
