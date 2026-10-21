<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class OAuthFlow implements JsonSerializable
{
    public function __construct(
        public ?string $authorizationUrl = null,
        public ?string $tokenUrl = null,
        public ?string $refreshUrl = null,
        public ?array $scopes = null,
        public ?string $deviceAuthorizationUrl = null,
        public ?bool $deprecated = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('authorizationUrl', 'authorizationUrl'),
            new FieldMetadata('tokenUrl', 'tokenUrl'),
            new FieldMetadata('refreshUrl', 'refreshUrl'),
            new FieldMetadata('scopes', 'scopes'),
            new FieldMetadata('deviceAuthorizationUrl', 'deviceAuthorizationUrl'),
            new FieldMetadata('deprecated', 'deprecated'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
