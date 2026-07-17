<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;

use function sprintf;

final class TooManyContainsValidationsError extends AbstractValidationError
{
    public function __construct(
        int $max,
        string|Breadcrumb $dataPath,
    ) {
        $message = sprintf(
            'Contains validation aborted after %d matching items (DoS defense)',
            $max,
        );

        parent::__construct(
            message: $message,
            keyword: 'contains',
            dataPath: $dataPath,
            schemaPath: '/contains',
            params: ['max' => $max],
            suggestion: 'Constrain the array with maxItems to keep contains validation bounded',
        );
    }
}
