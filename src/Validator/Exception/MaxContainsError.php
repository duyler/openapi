<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class MaxContainsError extends AbstractValidationError
{
    public function __construct(
        int $maxContains,
        int $minDetectedCount,
        string $dataPath,
        string $schemaPath,
    ) {
        $message = sprintf(
            'Array has at least %d matching items (detection stopped at threshold), but maximum contains is %d at %s',
            $minDetectedCount,
            $maxContains,
            $dataPath,
        );

        parent::__construct(
            message: $message,
            keyword: 'maxContains',
            dataPath: $dataPath,
            schemaPath: $schemaPath,
            params: ['maxContains' => $maxContains, 'minDetectedCount' => $minDetectedCount],
            suggestion: sprintf('Ensure array has at most %d matching items', $maxContains),
        );
    }
}
