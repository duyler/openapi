<?php

declare(strict_types=1);

namespace Duyler\OpenApi\Schema\Model;

use Duyler\OpenApi\Schema\Serializer\Internal\ModelWireSerializer;
use JsonSerializable;
use Override;

final readonly class Tag implements JsonSerializable
{
    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?ExternalDocs $externalDocs = null,
        public ?string $summary = null,
        public ?string $parent = null,
        public ?string $kind = null,
    ) {}

    /**
     * @return list<FieldMetadata>
     */
    public static function wireFields(): array
    {
        return [
            new FieldMetadata('name', 'name'),
            new FieldMetadata('description', 'description'),
            new FieldMetadata('externalDocs', 'externalDocs'),
            new FieldMetadata('summary', 'summary'),
            new FieldMetadata('parent', 'parent'),
            new FieldMetadata('kind', 'kind'),
        ];
    }

    #[Override]
    public function jsonSerialize(): array
    {
        return ModelWireSerializer::toArray($this, self::wireFields());
    }
}
