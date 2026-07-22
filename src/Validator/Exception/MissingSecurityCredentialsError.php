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
 *
 * SEC-032 / CWE-209 / CWE-497: scheme details are exposed only via
 * explicit opt-in getters that require $reveal = true from trusted
 * callers. Direct property access throws Error: Cannot access
 * protected property.
 */
final class MissingSecurityCredentialsError extends AbstractValidationError
{
    public function __construct(
        protected readonly string $schemeName,
        protected readonly string $schemeType,
        protected readonly string $location,
    ) {
        parent::__construct(
            message: 'Authentication required: missing or invalid credentials',
            keyword: 'security',
            dataPath: '/security',
            schemaPath: '/security',
            params: [],
        );
    }

    /**
     * Returns the security scheme name (e.g. 'bearerAuth'). Pass
     * $reveal = true only from trusted operator code; the default
     * returns '<redacted>' to prevent disclosure of the API's security
     * surface through reflective serialization.
     */
    public function schemeName(bool $reveal = false): string
    {
        return $reveal ? $this->schemeName : '<redacted>';
    }

    /**
     * Returns the security scheme type (e.g. 'http/bearer'). Pass
     * $reveal = true only from trusted operator code; the default
     * returns '<redacted>' to prevent disclosure of the API's security
     * surface through reflective serialization.
     */
    public function schemeType(bool $reveal = false): string
    {
        return $reveal ? $this->schemeType : '<redacted>';
    }

    /**
     * Returns the credential location description (e.g. 'Authorization
     * header'). Pass $reveal = true only from trusted operator code;
     * the default returns '<redacted>' to prevent disclosure of the
     * API's security surface through reflective serialization.
     */
    public function location(bool $reveal = false): string
    {
        return $reveal ? $this->location : '<redacted>';
    }
}
