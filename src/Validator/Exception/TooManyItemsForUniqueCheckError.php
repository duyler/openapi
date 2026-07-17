<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;

use function sprintf;

final class TooManyItemsForUniqueCheckError extends AbstractValidationError
{
    public function __construct(
        int $max,
        string|Breadcrumb $dataPath,
    ) {
        $message = sprintf(
            'Unique-items check aborted after %d unique entries (DoS defense)',
            $max,
        );

        parent::__construct(
            message: $message,
            keyword: 'uniqueItems',
            dataPath: $dataPath,
            schemaPath: '/uniqueItems',
            params: ['max' => $max],
            suggestion: 'Constrain the array with maxItems to keep unique-items check bounded',
        );
    }
}
