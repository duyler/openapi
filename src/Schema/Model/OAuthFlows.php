<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class OAuthFlows implements JsonSerializable
{
    public function __construct(
        public ?OAuthFlow $implicit = null,
        public ?OAuthFlow $password = null,
        public ?OAuthFlow $clientCredentials = null,
        public ?OAuthFlow $authorizationCode = null,
        public ?OAuthFlow $deviceCode = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('implicit', 'implicit', accessor: static fn(self $model): ?array => $model->implicit?->jsonSerialize()),
            new FieldMetadata('password', 'password', accessor: static fn(self $model): ?array => $model->password?->jsonSerialize()),
            new FieldMetadata('clientCredentials', 'clientCredentials', accessor: static fn(self $model): ?array => $model->clientCredentials?->jsonSerialize()),
            new FieldMetadata('authorizationCode', 'authorizationCode', accessor: static fn(self $model): ?array => $model->authorizationCode?->jsonSerialize()),
            new FieldMetadata('deviceCode', 'deviceCode', accessor: static fn(self $model): ?array => $model->deviceCode?->jsonSerialize()),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
