<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class MaxContainsError extends AbstractValidationError
{
    /**
     * @param int $minDetectedCount Lower bound of matches that triggered the violation, not a full count.
     *                               The validator loop breaks early once `maxContains + 1` matches are
     *                               found, so this value is the detection threshold (maxContains + 1),
     *                               never the true number of matching items in the array.
     */
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
