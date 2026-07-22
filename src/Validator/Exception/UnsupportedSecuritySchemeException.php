<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Exception;

use RuntimeException;

use function sprintf;

/**
 * Thrown when an OpenAPI security scheme uses a type that this package
 * does not validate. The validator supports `http/bearer` and `apiKey`
 * only; schemes such as `oauth2`, `openIdConnect`, `http/basic`,
 * `http/digest`, and `mutualTLS` require an external token validator
 * (introspection endpoint, JWT signature check, JWKS) and are rejected
 * explicitly so consumers learn about the configuration error instead
 * of seeing the scheme silently classified as "missing credentials".
 *
 * R4-SEC-010: misclassifying an unsupported scheme as a missing
 * credential both misleads callers (who believe they only need to
 * supply the credential) and breaks AND/OR semantics for mixed
 * security requirements.
 *
 * This exception is a configuration-time signal: a spec that mixes
 * supported and unsupported schemes is invalid under partial
 * validation, so the validator fails closed rather than silently
 * bypassing the unsupported requirement.
 */
final class UnsupportedSecuritySchemeException extends RuntimeException
{
    use SanitizableExceptionTrait;

    public function __construct(
        public readonly string $schemeName,
        public readonly string $schemeType,
        public readonly ?string $httpScheme = null,
    ) {
        $descriptor = null === $httpScheme ? $schemeType : sprintf('%s/%s', $schemeType, $httpScheme);

        parent::__construct(sprintf(
            'Security scheme "%s" uses unsupported type "%s". duyler/openapi validates apiKey and http/bearer only; '
            . 'oauth2, openIdConnect, http/basic, http/digest, and mutualTLS require an external token validator.',
            $schemeName,
            $descriptor,
        ));
    }
}
