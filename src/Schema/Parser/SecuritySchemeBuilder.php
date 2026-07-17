<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Parser;

use Duyler\OpenApi\Schema\Exception\InvalidSchemaException;
use Duyler\OpenApi\Schema\Model\OAuthFlow;
use Duyler\OpenApi\Schema\Model\OAuthFlows;
use Duyler\OpenApi\Schema\Model\SecurityScheme;

use function is_array;

/**
 * Builds OpenAPI SecurityScheme / OAuthFlows / OAuthFlow objects.
 *
 * Standalone group with no inbound dependencies on other sub-builders.
 * {@see ComponentsBuilder} delegates here for `components.securitySchemes`.
 */
final readonly class SecuritySchemeBuilder
{
    public function __construct(private OpenApiBuildContext $context) {}

    public function buildSecurityScheme(array $data): SecurityScheme
    {
        if (false === isset($data['type'])) {
            throw new InvalidSchemaException('Security scheme must have type');
        }

        return new SecurityScheme(
            type: TypeHelper::asString($data['type']),
            description: TypeHelper::asStringOrNull($data['description'] ?? null),
            name: TypeHelper::asStringOrNull($data['name'] ?? null),
            in: TypeHelper::asStringOrNull($data['in'] ?? null),
            scheme: TypeHelper::asStringOrNull($data['scheme'] ?? null),
            bearerFormat: TypeHelper::asStringOrNull($data['bearerFormat'] ?? null),
            flows: isset($data['flows']) && is_array($data['flows'])
                ? $this->buildOAuthFlows(TypeHelper::asArray($data['flows']))
                : null,
            openIdConnectUrl: TypeHelper::asStringOrNull($data['openIdConnectUrl'] ?? null),
            oauth2MetadataUrl: TypeHelper::asStringOrNull($data['oauth2MetadataUrl'] ?? null),
            authorizationUrl: TypeHelper::asStringOrNull($data['authorizationUrl'] ?? null),
            tokenUrl: TypeHelper::asStringOrNull($data['tokenUrl'] ?? null),
            refreshUrl: TypeHelper::asStringOrNull($data['refreshUrl'] ?? null),
            scopes: isset($data['scopes']) && is_array($data['scopes'])
                ? TypeHelper::asStringMap($data['scopes'])
                : null,
        );
    }

    public function buildOAuthFlows(array $data): OAuthFlows
    {
        return new OAuthFlows(
            implicit: isset($data['implicit']) && is_array($data['implicit'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['implicit']))
                : null,
            password: isset($data['password']) && is_array($data['password'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['password']))
                : null,
            clientCredentials: isset($data['clientCredentials']) && is_array($data['clientCredentials'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['clientCredentials']))
                : null,
            authorizationCode: isset($data['authorizationCode']) && is_array($data['authorizationCode'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['authorizationCode']))
                : null,
            deviceCode: isset($data['deviceCode']) && is_array($data['deviceCode'])
                ? $this->buildOAuthFlow(TypeHelper::asArray($data['deviceCode']))
                : null,
        );
    }

    public function buildOAuthFlow(array $data): OAuthFlow
    {
        return new OAuthFlow(
            authorizationUrl: TypeHelper::asStringOrNull($data['authorizationUrl'] ?? null),
            tokenUrl: TypeHelper::asStringOrNull($data['tokenUrl'] ?? null),
            refreshUrl: TypeHelper::asStringOrNull($data['refreshUrl'] ?? null),
            scopes: isset($data['scopes']) && is_array($data['scopes'])
                ? TypeHelper::asStringMap($data['scopes'])
                : null,
            deviceAuthorizationUrl: TypeHelper::asStringOrNull($data['deviceAuthorizationUrl'] ?? null),
            deprecated: TypeHelper::asBoolOrNull($data['deprecated'] ?? null),
        );
    }
}
