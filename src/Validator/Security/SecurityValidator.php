<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Dto\SecurityValidationContext;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Duyler\OpenApi\Validator\Exception\UnsupportedSecuritySchemeException;
use Duyler\OpenApi\Validator\Exception\ValidationException;
use Duyler\OpenApi\Validator\PregExecutor;
use Duyler\OpenApi\Validator\Security\Internal\ApiKeyValidator;
use Duyler\OpenApi\Validator\Security\Internal\BearerAuthValidator;
use Duyler\OpenApi\Validator\Security\Internal\SchemeCredentialReporter;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use function sprintf;
use function strtoupper;

final readonly class SecurityValidator
{
    private readonly LoggerInterface $logger;

    public function __construct(
        ?LoggerInterface $logger = null,
        private readonly PregExecutor $pregExecutor = new PregExecutor(),
        private readonly BearerAuthValidator $bearerValidator = new BearerAuthValidator(new PregExecutor()),
        private readonly ApiKeyValidator $apiKeyValidator = new ApiKeyValidator(),
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function validate(SecurityValidationContext $context): void
    {
        $allErrors = [];
        $request = $context->request;
        $securitySchemes = $context->securitySchemes;
        $unsupportedException = null;

        foreach ($context->securityRequirements->requirements as $requirementAlternatives) {
            try {
                $errors = $this->validateRequirement($request, $requirementAlternatives, $securitySchemes);
            } catch (UnsupportedSecuritySchemeException $e) {
                if (null === $unsupportedException) {
                    $unsupportedException = $e;
                }

                continue;
            }

            if ([] === $errors) {
                return;
            }

            $allErrors = [...$allErrors, ...$errors];
        }

        if (null !== $unsupportedException) {
            throw $unsupportedException;
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
                $errors[] = $this->reportMissing($schemeName, 'undefined', 'scheme not found in components/securitySchemes');

                continue;
            }

            if ([] !== $scopes) {
                $this->logger->debug('Security requirement scopes', [
                    'schemeName' => $schemeName,
                    'schemeType' => $scheme->type,
                    'scopes' => $scopes,
                ]);
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
        $reporter = new SchemeCredentialReporter($schemeName, $this->logger);

        return match ($scheme->type) {
            'http' => $this->bearerValidator->validate($request, $scheme, $reporter),
            'apiKey' => $this->apiKeyValidator->validate($request, $scheme, $reporter),
            default => throw new UnsupportedSecuritySchemeException(
                schemeName: $schemeName,
                schemeType: $scheme->type,
            ),
        };
    }

    private function reportMissing(string $schemeName, string $schemeType, string $location): MissingSecurityCredentialsError
    {
        return new SchemeCredentialReporter($schemeName, $this->logger)->reportMissing($schemeType, $location);
    }
}
