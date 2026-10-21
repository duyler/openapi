<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class SecurityScheme implements JsonSerializable
{
    /**
     * @param array<string, string>|null $scopes
     */
    public function __construct(
        public string $type,
        public ?string $description = null,
        public ?string $name = null,
        public ?string $in = null,
        public ?string $scheme = null,
        public ?string $bearerFormat = null,
        public ?OAuthFlows $flows = null,
        public ?string $openIdConnectUrl = null,
        public ?string $oauth2MetadataUrl = null,
        public ?string $authorizationUrl = null,
        public ?string $tokenUrl = null,
        public ?string $refreshUrl = null,
        public ?array $scopes = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('type', 'type'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('name', 'name'),
            new FieldMetadata('in', 'in'),
            new FieldMetadata('scheme', 'scheme'),
            new FieldMetadata('bearerFormat', 'bearerFormat'),
            new FieldMetadata('flows', 'flows', accessor: static fn(self $model): ?array => $model->flows?->jsonSerialize()),
            new FieldMetadata('openIdConnectUrl', 'openIdConnectUrl'),
            new FieldMetadata('oauth2MetadataUrl', 'oauth2MetadataUrl'),
            new FieldMetadata('authorizationUrl', 'authorizationUrl'),
            new FieldMetadata('tokenUrl', 'tokenUrl'),
            new FieldMetadata('refreshUrl', 'refreshUrl'),
            new FieldMetadata('scopes', 'scopes'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
