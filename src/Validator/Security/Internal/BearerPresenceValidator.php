<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\PregRuntimeException;
use Duyler\OpenApi\Validator\PregExecutor;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

/** @internal */
final readonly class BearerPresenceValidator
{
    public function __construct(
        private readonly PregExecutor $pregExecutor,
        private readonly LoggerInterface $logger,
    ) {}

    public function validate(
        ServerRequestInterface $request,
        SecurityScheme $scheme,
        SchemeCredentialReporter $reporter,
    ): ?MissingSecurityCredentialsError {
        $authorization = $request->getHeaderLine('Authorization');

        try {
            $matched = $this->pregExecutor->match(BearerAuthValidator::BEARER_AUTH_PATTERN, $authorization);
        } catch (PregRuntimeException) {
            $matched = 0;
        }

        if (1 !== $matched) {
            return $reporter->reportMissing($scheme->type, 'Authorization header');
        }

        $declaredScopes = $this->declaredScopes($scheme);

        if ([] !== $declaredScopes) {
            $this->logger->debug('Security scheme declared scopes', [
                'schemeName' => $reporter->schemeName,
                'schemeType' => $scheme->type,
                'scopes' => $declaredScopes,
            ]);
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private function declaredScopes(SecurityScheme $scheme): array
    {
        $scopes = [];

        $flows = $scheme->flows;

        if (null !== $flows) {
            foreach ([
                $flows->implicit,
                $flows->password,
                $flows->clientCredentials,
                $flows->authorizationCode,
                $flows->deviceAuthorization,
            ] as $flow) {
                if (null !== $flow && null !== $flow->scopes) {
                    $scopes += $flow->scopes;
                }
            }
        }

        if (null !== $scheme->scopes) {
            $scopes += $scheme->scopes;
        }

        return $scopes;
    }
}
