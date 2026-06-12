<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\SchemaValidator;

use Duyler\OpenApi\Validator\Error\Breadcrumb;
use Duyler\OpenApi\Validator\Exception\AbstractValidationError;

use function sprintf;

final class InvalidContentEncodingException extends AbstractValidationError
{
    public function __construct(
        string $data,
        string $encoding,
    ) {
        parent::__construct(
            message: sprintf('Value is not valid %s-encoded string', $encoding),
            keyword: 'contentEncoding',
            dataPath: new Breadcrumb([]),
            schemaPath: '/contentEncoding',
            params: ['encoding' => $encoding, 'value' => $data],
        );
    }
}
