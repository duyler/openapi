<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema;

use Duyler\OpenApi\Schema\Model\FieldMetadata;
use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class OpenApiDocument implements JsonSerializable
{
    public function __construct(
        public string $openapi,
        public Model\InfoObject $info,
        public ?string $jsonSchemaDialect = null,
        public ?Model\Servers $servers = null,
        public ?Model\Paths $paths = null,
        public ?Model\Webhooks $webhooks = null,
        public ?Model\Components $components = null,
        public ?Model\SecurityRequirement $security = null,
        public ?Model\Tags $tags = null,
        public ?Model\ExternalDocs $externalDocs = null,
        public ?string $self = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('openapi', 'openapi'),
            new FieldMetadata('info', 'info'),
            new FieldMetadata('jsonSchemaDialect', 'jsonSchemaDialect'),
            new FieldMetadata('servers', 'servers'),
            new FieldMetadata('paths', 'paths'),
            new FieldMetadata('webhooks', 'webhooks'),
            new FieldMetadata('components', 'components'),
            new FieldMetadata('security', 'security'),
            new FieldMetadata('tags', 'tags'),
            new FieldMetadata('externalDocs', 'externalDocs'),
            new FieldMetadata('self', '$self'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
