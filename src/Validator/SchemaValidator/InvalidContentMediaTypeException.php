<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\Breadcrumb;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;

use function sprintf;

final class InvalidContentMediaTypeException extends AbstractValidationError
{
    public function __construct(
        string $data,
        string $mediaType,
    ) {
        parent::__construct(
            message: sprintf('Value does not match the declared content media type "%s"', $mediaType),
            keyword: 'contentMediaType',
            dataPath: new Breadcrumb([]),
            schemaPath: '/contentMediaType',
            params: ['mediaType' => $mediaType, 'value' => $data],
        );
    }
}
