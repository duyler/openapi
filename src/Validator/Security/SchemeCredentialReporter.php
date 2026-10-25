<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Psr\Log\LoggerInterface;

/**
 * Creates caller-safe missing-credential errors for one security scheme.
 *
 * A handler implementation receives the reporter bound to the validated scheme
 * name and uses reportMissing() to describe where the credentials were expected;
 * the produced error never carries request data, and the verbose log entry stays
 * limited to the scheme name, scheme type and location.
 */
final readonly class SchemeCredentialReporter
{
    public function __construct(
        public string $schemeName,
        private LoggerInterface $logger,
    ) {}

    public function reportMissing(string $schemeType, string $location): MissingSecurityCredentialsError
    {
        $this->logger->debug('Security validation failed', [
            'schemeName' => $this->schemeName,
            'schemeType' => $schemeType,
            'location' => $location,
        ]);

        return new MissingSecurityCredentialsError(
            schemeName: $this->schemeName,
            schemeType: $schemeType,
            location: $location,
        );
    }
}
