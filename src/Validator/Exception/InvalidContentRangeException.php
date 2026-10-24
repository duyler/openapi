<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use Duyler\OpenApi\Validator\Error\Breadcrumb;

use function sprintf;

final class InvalidContentRangeException extends AbstractValidationError
{
    public static function malformedSyntax(): self
    {
        return new self(
            message: 'The part Content-Range header value is not a valid byte range per RFC 9110',
            keyword: 'format',
            dataPath: new Breadcrumb([]),
            schemaPath: '/headers',
            params: ['header' => 'Content-Range'],
            suggestion: 'Send a well-formed byte range such as "bytes 0-99/1000", "bytes 0-99/*" or "bytes */1000"',
        );
    }

    public static function invertedRange(int $first, int $last): self
    {
        return new self(
            message: sprintf(
                'The part Content-Range header declares the last byte position %d before the first byte position %d',
                $last,
                $first,
            ),
            keyword: 'format',
            dataPath: new Breadcrumb([]),
            schemaPath: '/headers',
            params: ['header' => 'Content-Range', 'first' => $first, 'last' => $last],
            suggestion: 'Send a byte range whose last byte position is greater than or equal to the first byte position',
        );
    }
}
