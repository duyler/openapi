<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Operation implements JsonSerializable
{
    /**
     * @param list<string>|null $tags
     */
    public function __construct(
        public ?array $tags = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?ExternalDocs $externalDocs = null,
        public ?string $operationId = null,
        public ?Parameters $parameters = null,
        public ?RequestBody $requestBody = null,
        public ?Responses $responses = null,
        public ?Callbacks $callbacks = null,
        public bool $deprecated = false,
        public ?SecurityRequirement $security = null,
        public ?Servers $servers = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('tags', 'tags'),
            new FieldMetadata('summary', 'summary'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('externalDocs', 'externalDocs'),
            new FieldMetadata('operationId', 'operationId'),
            new FieldMetadata('parameters', 'parameters'),
            new FieldMetadata('requestBody', 'requestBody'),
            new FieldMetadata('responses', 'responses'),
            new FieldMetadata('callbacks', 'callbacks'),
            new FieldMetadata('deprecated', 'deprecated', accessor: static fn(self $model): ?bool => $model->deprecated ? true : null),
            new FieldMetadata('security', 'security'),
            new FieldMetadata('servers', 'servers'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
