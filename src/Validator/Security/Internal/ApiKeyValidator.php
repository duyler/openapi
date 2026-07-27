<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Validator\Security\Internal;

use Duyler\OpenApi\Schema\Model\SecurityScheme;
use Duyler\OpenApi\Validator\Exception\MissingSecurityCredentialsError;
use Psr\Http\Message\ServerRequestInterface;

use function is_string;
use function sprintf;

/** @internal */
final readonly class ApiKeyValidator
{
    public function validate(ServerRequestInterface $request, SecurityScheme $scheme, SchemeCredentialReporter $reporter): ?MissingSecurityCredentialsError
    {
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

        return $reporter->reportMissing('apiKey', $reason);
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
