<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function sprintf;
use function str_starts_with;
use function strtolower;

final readonly class SecurityValidator
{
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

        return new MissingSecurityCredentialsError(
            schemeName: $schemeName,
            schemeType: 'apiKey',
            location: $reason,
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
