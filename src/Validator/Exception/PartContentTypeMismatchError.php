<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;

use function sprintf;

final class PartContentTypeMismatchError extends AbstractValidationError
{
    public function __construct(
        string $keyword,
        string $expected,
        string $actual,
        int $partIndex,
    ) {
        parent::__construct(
            message: sprintf('Part %d declares content type "%s" which does not match the encoding "%s"', $partIndex, $actual, $expected),
            keyword: $keyword,
            dataPath: new Breadcrumb([]),
            schemaPath: '/' . $keyword,
            params: ['partIndex' => $partIndex, 'expected' => $expected, 'actual' => $actual],
            suggestion: sprintf('Change the content type of part %d to "%s" or update the encoding declaration', $partIndex, $expected),
        );
    }
}
