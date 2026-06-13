<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use function sprintf;

final class MissingSecurityCredentialsError extends AbstractValidationError
{
    public function __construct(
        string $schemeName,
        string $schemeType,
        string $location,
    ) {
        parent::__construct(
            message: sprintf(
                'Security credentials missing for scheme "%s" (type: %s). Expected in: %s',
                $schemeName,
                $schemeType,
                $location,
            ),
            keyword: 'security',
            dataPath: '/security/' . $schemeName,
            schemaPath: '#/components/securitySchemes/' . $schemeName,
            params: [
                'schemeName' => $schemeName,
                'schemeType' => $schemeType,
                'location' => $location,
            ],
        );
    }
}
