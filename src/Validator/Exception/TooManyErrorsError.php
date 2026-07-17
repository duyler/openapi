<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;

use function sprintf;

final class TooManyErrorsError extends AbstractValidationError
{
    public function __construct(
        int $max,
        string|Breadcrumb $dataPath,
    ) {
        $message = sprintf(
            'Too many errors, validation truncated at %d',
            $max,
        );

        parent::__construct(
            message: $message,
            keyword: 'composition',
            dataPath: $dataPath,
            schemaPath: '/composition',
            params: ['max' => $max],
            suggestion: sprintf('Limit composition schemas to produce at most %d errors', $max),
        );
    }
}
