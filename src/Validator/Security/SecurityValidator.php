<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\PregExecutor;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function is_string;
use function sprintf;
use function strtolower;
use function strtoupper;

final readonly class SecurityValidator
{
    private readonly LoggerInterface $logger;

    public function __construct(
        ?LoggerInterface $logger = null,
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function validate(SecurityValidationContext $context): void
    {
        $allErrors = [];
        $request = $context->request;
        $securitySchemes = $context->securitySchemes;

        foreach ($context->securityRequirements->requirements as $requirementAlternatives) {
            $errors = $this->validateRequirement($request, $requirementAlternatives, $securitySchemes);

            if ([] === $errors) {
                return;
            }

            $allErrors = [...$allErrors, ...$errors];
        }

        if ([] !== $allErrors) {
            throw new ValidationException(
                message: sprintf(
                    'Security validation failed for %s %s',
                    strtoupper($context->method),
                    $context->path,
                ),
                errors: $allErrors,
            );
        }
    }

    /**
     * @param array<string, list<string>> $requirementAlternatives
     * @param array<string, SecurityScheme> $securitySchemes
     *
     * @return list<MissingSecurityCredentialsError>
     */
    private function validateRequirement(
        ServerRequestInterface $request,
        array $requirementAlternatives,
        array $securitySchemes,
    ): array {
        $errors = [];

        foreach ($requirementAlternatives as $schemeName => $scopes) {
            $scheme = $securitySchemes[$schemeName] ?? null;

            if (null === $scheme) {
                $errors[] = $this->reportMissingCredentials(
                    $schemeName,
                    'undefined',
                    'scheme not found in components/securitySchemes',
                );

                continue;
            }

            $error = $this->validateScheme($request, $schemeName, $scheme);

            if (null !== $error) {
                $errors[] = $error;
            }
        }

        return $errors;
    }

    private function validateScheme(
        ServerRequestInterface $request,
        string $schemeName,
        SecurityScheme $scheme,
    ): ?MissingSecurityCredentialsError {
        return match ($scheme->type) {
            'http' => $this->validateHttpScheme($request, $schemeName, $scheme),
            'apiKey' => $this->validateApiKeyScheme($request, $schemeName, $scheme),
            default => $this->reportMissingCredentials(
                $schemeName,
                $scheme->type,
                'unsupported scheme type',
            ),
        };
    }

    private function validateHttpScheme(
        ServerRequestInterface $request,
        string $schemeName,
        SecurityScheme $scheme,
    ): ?MissingSecurityCredentialsError {
        $schemeType = strtolower($scheme->scheme ?? 'bearer');

        if ('bearer' !== $schemeType) {
            return $this->reportMissingCredentials(
                $schemeName,
                sprintf('http/%s', $schemeType),
                'unsupported http scheme',
            );
        }

        $authorization = $request->getHeaderLine('Authorization');

        // RFC 6750 §2.1: Bearer scheme is case-insensitive (BEARER,
        // Bearer, bearer all valid). RFC 9110 OWS permits one or more
        // whitespace characters between scheme and token. RFC 6750 §2.1
        // requires a non-empty b64token after the scheme prefix.
        if ('' !== $authorization && 1 === $this->pregExecutor->match('/^bearer\s+\S+/i', $authorization)) {
            return null;
        }

        return $this->reportMissingCredentials(
            $schemeName,
            'http/bearer',
            'Authorization header',
        );
    }

    private function validateApiKeyScheme(
        ServerRequestInterface $request,
        string $schemeName,
        SecurityScheme $scheme,
    ): ?MissingSecurityCredentialsError {
        $location = $scheme->in ?? 'header';
        $name = $scheme->name ?? 'X-API-Key';

        /** @var string|null $value */
        $value = match ($location) {
            'query' => $this->findQueryCredential($request, $name),
            'header' => $this->findHeaderCredential($request, $name),
            'cookie' => $this->findCookieCredential($request, $name),
            default => null,
        };

        if (null !== $value && '' !== $value) {
            return null;
        }

        $reason = null === $value
            ? sprintf('missing %s parameter "%s"', $location, $name)
            : sprintf('empty %s parameter "%s"', $location, $name);

        return $this->reportMissingCredentials(
            $schemeName,
            'apiKey',
            $reason,
        );
    }

    /**
     * SEC-07 / CWE-209: emit a MissingSecurityCredentialsError with a
     * generic caller-safe message while forwarding the concrete scheme
     * details (schemeName, schemeType, location) to the PSR-3 logger at
     * debug level. The details never reach the exception message and
     * never reach the params() array consumed by error formatters, so
     * they cannot leak to unauthenticated callers via a PSR-15
     * middleware that surfaces Throwable messages.
     */
    private function reportMissingCredentials(
        string $schemeName,
        string $schemeType,
        string $location,
    ): MissingSecurityCredentialsError {
        $this->logger->debug('Security validation failed', [
            'schemeName' => $schemeName,
            'schemeType' => $schemeType,
            'location' => $location,
        ]);

        return new MissingSecurityCredentialsError(
            schemeName: $schemeName,
            schemeType: $schemeType,
            location: $location,
        );
    }

    private function findQueryCredential(ServerRequestInterface $request, string $name): ?string
    {
        $params = $request->getQueryParams();

        /** @var string|null $value */
        $value = $params[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    private function findHeaderCredential(ServerRequestInterface $request, string $name): ?string
    {
        $value = $request->getHeaderLine($name);

        return '' === $value ? null : $value;
    }

    private function findCookieCredential(ServerRequestInterface $request, string $name): ?string
    {
        $cookies = $request->getCookieParams();

        /** @var string|null $value */
        $value = $cookies[$name] ?? null;

        return is_string($value) ? $value : null;
    }
}
