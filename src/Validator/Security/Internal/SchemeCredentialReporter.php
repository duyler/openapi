<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Psr\Log\LoggerInterface;

/** @internal */
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
