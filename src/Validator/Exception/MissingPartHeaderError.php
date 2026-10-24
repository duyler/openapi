<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;

use function sprintf;

final class MissingPartHeaderError extends AbstractValidationError
{
    public function __construct(
        string $headerName,
        int $partIndex,
    ) {
        parent::__construct(
            message: sprintf('Required header "%s" is missing on part %d', $headerName, $partIndex),
            keyword: 'required',
            dataPath: new Breadcrumb([]),
            schemaPath: '/headers',
            params: ['property' => $headerName, 'partIndex' => $partIndex],
            suggestion: sprintf('Add the "%s" header to part %d', $headerName, $partIndex),
        );
    }
}
