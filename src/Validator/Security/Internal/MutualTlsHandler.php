<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Security\SecuritySchemeHandlerInterface;
use Override;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/** @internal */
final readonly class MutualTlsHandler implements SecuritySchemeHandlerInterface
{
    /**
     * @var list<string>
     */
    private const array TERMINATED_TLS_HEADER_CANDIDATES = [
        'SSL_CLIENT_CERT',
        'X-SSL-CERT',
        'X-Client-Cert',
    ];

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    #[Override]
    public function supports(SecurityScheme $scheme): bool
    {
        return 'mutualTLS' === $scheme->type;
    }

    #[Override]
    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        foreach (self::TERMINATED_TLS_HEADER_CANDIDATES as $headerName) {
            if ('' !== $request->getHeaderLine($headerName)) {
                $this->logger->warning('mutualTLS presence detected via proxy header; trusting proxy configuration required');

                return null;
            }
        }

        return $reporter->reportMissing('mutualTLS', 'TLS client certificate headers');
    }
}
