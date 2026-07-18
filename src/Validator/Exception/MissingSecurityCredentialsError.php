<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

/**
 * Thrown when a security scheme requirement is not satisfied by the
 * incoming request.
 *
 * SEC-07 / CWE-209: the public exception message is intentionally
 * generic so an unauthenticated caller cannot enumerate the API's
 * security surface (scheme names, parameter locations, header names).
 * The concrete scheme details are still available as readonly
 * properties on this object for programmatic access by trusted
 * operators, and the {@see SecurityValidator} forwards them to a
 * PSR-3 logger at debug level when one is configured.
 */
final class MissingSecurityCredentialsError extends AbstractValidationError
{
    public function __construct(
        public readonly string $schemeName,
        public readonly string $schemeType,
        public readonly string $location,
    ) {
        parent::__construct(
            message: 'Authentication required: missing or invalid credentials',
            keyword: 'security',
            dataPath: '/security',
            schemaPath: '/security',
            params: [],
        );
    }
}
