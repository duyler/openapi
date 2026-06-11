<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityRequirement;
use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

use function sprintf;
use function str_starts_with;
use function strtolower;

final readonly class SecurityValidator
{
    /**
     * @param SecurityRequirement $securityRequirements resolved security requirements (operation-level or global)
     * @param array<string, SecurityScheme> $securitySchemes security schemes from components
     */
    public function validate(
        ServerRequestInterface $request,
        string $path,
        string $method,
        SecurityRequirement $securityRequirements,
        array $securitySchemes,
    ): void {
        $allErrors = [];

        foreach ($securityRequirements->requirements as $requirementAlternatives) {
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
                    strtoupper($method),
                    $path,
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
                $errors[] = new MissingSecurityCredentialsError(
                    schemeName: $schemeName,
                    schemeType: 'undefined',
                    location: 'scheme not found in components/securitySchemes',
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
            default => new MissingSecurityCredentialsError(
                schemeName: $schemeName,
                schemeType: $scheme->type,
                location: 'unsupported scheme type',
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
            return new MissingSecurityCredentialsError(
                schemeName: $schemeName,
                schemeType: sprintf('http/%s', $schemeType),
                location: 'unsupported http scheme',
            );
        }

        $authorization = $request->getHeaderLine('Authorization');

        if ('' !== $authorization && str_starts_with($authorization, 'Bearer ')) {
            return null;
        }

        return new MissingSecurityCredentialsError(
            schemeName: $schemeName,
            schemeType: 'http/bearer',
            location: 'Authorization header',
        );
    }

    private function validateApiKeyScheme(
        ServerRequestInterface $request,
        string $schemeName,
        SecurityScheme $scheme,
    ): ?MissingSecurityCredentialsError {
        $location = $scheme->in ?? 'header';
        $name = $scheme->name ?? 'X-API-Key';

        $credentialPresent = match ($location) {
            'query' => $this->hasQueryParameter($request, $name),
            'header' => '' !== $request->getHeaderLine($name),
            'cookie' => $this->hasCookie($request, $name),
            default => false,
        };

        if ($credentialPresent) {
            return null;
        }

        return new MissingSecurityCredentialsError(
            schemeName: $schemeName,
            schemeType: 'apiKey',
            location: sprintf('%s "%s"', $location, $name),
        );
    }

    private function hasQueryParameter(ServerRequestInterface $request, string $name): bool
    {
        $params = $request->getQueryParams();

        return isset($params[$name]) && '' !== $params[$name];
    }

    private function hasCookie(ServerRequestInterface $request, string $name): bool
    {
        $cookies = $request->getCookieParams();

        return isset($cookies[$name]) && '' !== $cookies[$name];
    }
}
